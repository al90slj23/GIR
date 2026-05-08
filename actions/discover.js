const runType = process.env.RUN_TYPE || process.argv[2] || "daily";
const periodType = runType === "weekly" ? "weekly" : runType === "manual" ? "manual" : "daily";
const ingestBatchSize = Math.max(1, Number(process.env.INGEST_BATCH_SIZE || "1"));

const env = {
  githubToken: process.env.GITHUB_TOKEN || "",
  deepseekKey: process.env.DEEPSEEK_API_KEY || "",
  deepseekBaseUrl: process.env.DEEPSEEK_BASE_URL || "https://api.deepseek.com",
  deepseekModel: process.env.DEEPSEEK_MODEL || "deepseek-chat",
  ingestUrl: process.env.APP_INGEST_URL || "",
  ingestToken: process.env.APP_INGEST_TOKEN || "",
  configUrl: process.env.APP_CONFIG_URL || "",
};

for (const [key, value] of Object.entries(env)) {
  if (!value && ["deepseekKey", "ingestUrl", "ingestToken"].includes(key)) {
    throw new Error(`Missing required environment: ${key}`);
  }
}

const defaultConfig = {
  max_projects: Number(process.env.MAX_PROJECTS || "10"),
  per_page: 20,
  recent_days_daily: 3,
  recent_days_weekly: 14,
  min_stars_general: 100,
  min_stars_created: 20,
  min_stars_topic: 50,
  min_stars_agent: 30,
  topics: ["ai", "llm", "agent"],
  extra_queries: [],
};

const today = new Date().toISOString().slice(0, 10);

const ghHeaders = {
  "Accept": "application/vnd.github+json",
  "User-Agent": "GIR-Discover",
  ...(env.githubToken ? { "Authorization": `Bearer ${env.githubToken}` } : {}),
};

function clampNumber(value, fallback, min, max) {
  const number = Number(value);
  if (!Number.isFinite(number)) return fallback;
  return Math.max(min, Math.min(max, Math.floor(number)));
}

function normalizeList(value) {
  if (Array.isArray(value)) {
    return [...new Set(value.map((item) => String(item).trim()).filter(Boolean))];
  }
  return [...new Set(String(value || "").split(/[\n,]+/).map((item) => item.trim()).filter(Boolean))];
}

function deriveConfigUrl() {
  if (env.configUrl) return env.configUrl;
  if (/\/api\/receive_projects\.php($|\?)/.test(env.ingestUrl)) {
    return env.ingestUrl.replace(/\/api\/receive_projects\.php($|\?.*)/, "/api/discover_config.php");
  }
  return "";
}

async function loadDiscoverConfig() {
  const url = deriveConfigUrl();
  if (!url) return defaultConfig;
  try {
    const res = await fetch(url, {
      headers: {
        "Accept": "application/json",
        "Authorization": `Bearer ${env.ingestToken}`,
        "User-Agent": "GIR-Discover",
      },
    });
    if (!res.ok) {
      console.warn(`Config ${res.status}: using defaults`);
      return defaultConfig;
    }
    const data = await res.json();
    const config = data?.config || {};
    return {
      max_projects: clampNumber(config.max_projects, defaultConfig.max_projects, 1, 50),
      per_page: clampNumber(config.per_page, defaultConfig.per_page, 1, 100),
      recent_days_daily: clampNumber(config.recent_days_daily, defaultConfig.recent_days_daily, 1, 90),
      recent_days_weekly: clampNumber(config.recent_days_weekly, defaultConfig.recent_days_weekly, 1, 180),
      min_stars_general: clampNumber(config.min_stars_general, defaultConfig.min_stars_general, 0, 1000000),
      min_stars_created: clampNumber(config.min_stars_created, defaultConfig.min_stars_created, 0, 1000000),
      min_stars_topic: clampNumber(config.min_stars_topic, defaultConfig.min_stars_topic, 0, 1000000),
      min_stars_agent: clampNumber(config.min_stars_agent, defaultConfig.min_stars_agent, 0, 1000000),
      topics: normalizeList(config.topics).length ? normalizeList(config.topics) : defaultConfig.topics,
      extra_queries: normalizeList(config.extra_queries),
    };
  } catch (error) {
    console.warn(`Config error: ${error.message}; using defaults`);
    return defaultConfig;
  }
}

function buildQuerySpecs(config) {
  const recentDays = periodType === "weekly" ? config.recent_days_weekly : config.recent_days_daily;
  const since = new Date(Date.now() - recentDays * 86400000).toISOString().slice(0, 10);
  const specs = [
    { platform: "github", tag: "综合", query: `stars:>${config.min_stars_general} pushed:>${since}` },
    { platform: "github", tag: "新项目", query: `created:>${since} stars:>${config.min_stars_created}` },
  ];
  for (const topic of config.topics) {
    const minStars = topic.toLowerCase() === "agent" ? config.min_stars_agent : config.min_stars_topic;
    specs.push({ platform: "github", tag: topic, query: `topic:${topic} pushed:>${since} stars:>${minStars}` });
  }
  for (const [index, query] of config.extra_queries.entries()) {
    specs.push({
      platform: "github",
      tag: `自定义${index + 1}`,
      query: query.replaceAll("{since}", since),
    });
  }
  const seen = new Set();
  return specs.filter((spec) => {
    const key = `${spec.platform}\n${spec.tag}\n${spec.query}`;
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });
}

async function githubJson(url) {
  const res = await fetch(url, { headers: ghHeaders });
  if (!res.ok) {
    throw new Error(`GitHub API ${res.status}: ${await res.text()}`);
  }
  return res.json();
}

async function searchProjects(config) {
  const map = new Map();
  const specs = buildQuerySpecs(config);
  for (const spec of specs) {
    const url = new URL("https://api.github.com/search/repositories");
    url.searchParams.set("q", spec.query);
    url.searchParams.set("sort", "stars");
    url.searchParams.set("order", "desc");
    url.searchParams.set("per_page", String(config.per_page));
    const data = await githubJson(url.toString());
    let queryRank = 0;
    for (const repo of data.items || []) {
      if (repo.archived || repo.disabled || repo.fork) continue;
      queryRank++;
      const sourceScore = scoreRepo(repo);
      if (!map.has(repo.full_name)) {
        repo.source_platform = spec.platform;
        repo.source_tag = spec.tag;
        repo.source_rank = queryRank;
        repo.source_score = sourceScore;
        map.set(repo.full_name, repo);
      } else {
        const current = map.get(repo.full_name);
        if (sourceScore > Number(current.source_score || 0)) {
          current.source_platform = spec.platform;
          current.source_tag = spec.tag;
          current.source_rank = queryRank;
          current.source_score = sourceScore;
        }
      }
    }
  }
  return [...map.values()]
    .sort((a, b) => scoreRepo(b) - scoreRepo(a))
    .slice(0, config.max_projects)
    .map((repo, index) => ({
      ...repo,
      source_rank: index + 1,
      source_score: scoreRepo(repo),
    }));
}

function scoreRepo(repo) {
  const pushed = repo.pushed_at ? Date.parse(repo.pushed_at) : 0;
  const freshness = pushed ? Math.max(0, 30 - (Date.now() - pushed) / 86400000) : 0;
  return Number(repo.stargazers_count || 0) + freshness * 20 + Number(repo.forks_count || 0) * 2;
}

async function getReadme(repo) {
  const url = `https://api.github.com/repos/${repo.full_name}/readme`;
  try {
    const data = await githubJson(url);
    if (!data.download_url) return "";
    const res = await fetch(data.download_url, { headers: { "User-Agent": "GIR-Discover" } });
    if (!res.ok) return "";
    const text = await res.text();
    return text.slice(0, 12000);
  } catch {
    return "";
  }
}

function systemPrompt() {
  return [
    "你是一个帮助站长发现 GitHub 新项目的技术分析员。",
    "站长的部署环境是传统 PHP 7.2 + MySQL 5.1 虚拟主机，不能运行 Docker、Node/Python 常驻服务、本地模型或 WebSocket。",
    "你必须用中文输出严格 JSON，不要 Markdown，不要解释。",
    "评分为 1 到 10 的整数。",
  ].join("\n");
}

function userPrompt(repo, readme) {
  return JSON.stringify({
    task: "分析这个 GitHub 项目是否值得关注，以及是否适合改造成轻量 PHP 虚拟主机项目。",
    required_schema: {
      one_sentence: "一句话介绍",
      project_type: "工具/框架/游戏/Agent/UI/后端/数据集/其他",
      problem: "解决什么问题",
      tech_stack: ["主要技术栈"],
      target_users: ["适合谁用"],
      play_score: "1-10",
      useful_score: "1-10",
      php_fit_score: "1-10",
      difficulty: "低/中/高",
      is_suitable_for_this_host: true,
      ideas_to_reuse: ["可借鉴点"],
      risks: ["风险点"],
      recommendation: "收藏/研究/可复刻/暂不关注",
      summary_zh: "中文总结",
    },
    repo: {
      full_name: repo.full_name,
      description: repo.description,
      stars: repo.stargazers_count,
      forks: repo.forks_count,
      language: repo.language,
      topics: repo.topics || [],
      html_url: repo.html_url,
    },
    readme,
  });
}

async function analyze(repo, readme) {
  const res = await fetch(`${env.deepseekBaseUrl.replace(/\/$/, "")}/v1/chat/completions`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "Authorization": `Bearer ${env.deepseekKey}`,
    },
    body: JSON.stringify({
      model: env.deepseekModel,
      temperature: 0.2,
      response_format: { type: "json_object" },
      messages: [
        { role: "system", content: systemPrompt() },
        { role: "user", content: userPrompt(repo, readme) },
      ],
    }),
  });
  if (!res.ok) {
    throw new Error(`DeepSeek ${res.status}: ${await res.text()}`);
  }
  const data = await res.json();
  const content = data?.choices?.[0]?.message?.content || "{}";
  return JSON.parse(content);
}

async function ingestBatch(projects) {
  const payload = {
    run_type: runType,
    period_type: periodType,
    report_date: today,
    projects,
  };
  const form = new URLSearchParams();
  form.set("payload_b64", Buffer.from(JSON.stringify(payload), "utf8").toString("base64"));

  const res = await fetch(env.ingestUrl, {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
      "Authorization": `Bearer ${env.ingestToken}`,
    },
    body: form.toString(),
  });
  const text = await res.text();
  if (!res.ok) {
    throw new Error(`Ingest ${res.status}: ${text}`);
  }
  console.log(text);
}

async function ingest(projects) {
  for (let index = 0; index < projects.length; index += ingestBatchSize) {
    await ingestBatch(projects.slice(index, index + ingestBatchSize));
  }
}

function projectPayload(repo, analysis) {
  return {
    github_id: repo.id,
    name: repo.name,
    full_name: repo.full_name,
    html_url: repo.html_url,
    description: repo.description || "",
    stars: repo.stargazers_count || 0,
    forks: repo.forks_count || 0,
    language: repo.language || "",
    topics: repo.topics || [],
    pushed_at: repo.pushed_at,
    source_platform: repo.source_platform || "github",
    source_tag: repo.source_tag || "综合",
    source_rank: repo.source_rank || 0,
    source_score: repo.source_score || 0,
    analysis,
  };
}

async function main() {
  const config = await loadDiscoverConfig();
  console.log(`Discover config: max=${config.max_projects}, per_page=${config.per_page}, topics=${config.topics.join(",")}`);
  const repos = await searchProjects(config);
  const projects = [];
  for (const repo of repos) {
    console.log(`Analyzing ${repo.full_name}`);
    try {
      const readme = await getReadme(repo);
      const analysis = await analyze(repo, readme);
      projects.push(projectPayload(repo, analysis));
    } catch (error) {
      console.error(`Failed ${repo.full_name}: ${error.message}`);
    }
  }
  if (!projects.length) {
    throw new Error("No projects analyzed");
  }
  await ingest(projects);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
