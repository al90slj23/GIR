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
  platforms: ["github_trending", "github_search", "ossinsight", "trendshift", "reporank", "gitrepotrend"],
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
      platforms: normalizeList(config.platforms).length ? normalizeList(config.platforms) : defaultConfig.platforms,
    };
  } catch (error) {
    console.warn(`Config error: ${error.message}; using defaults`);
    return defaultConfig;
  }
}

function platformEnabled(config, platform) {
  return config.platforms.includes(platform);
}

function periodToSince() {
  return periodType === "weekly" ? "weekly" : "daily";
}

async function fetchText(url) {
  const res = await fetch(url, {
    headers: {
      "Accept": "text/html,application/xhtml+xml,application/json",
      "User-Agent": "GIR-Discover",
    },
  });
  if (!res.ok) {
    throw new Error(`${url} ${res.status}: ${await res.text()}`);
  }
  return res.text();
}

async function fetchJson(url) {
  const res = await fetch(url, {
    headers: {
      "Accept": "application/json",
      "User-Agent": "GIR-Discover",
    },
  });
  if (!res.ok) {
    throw new Error(`${url} ${res.status}: ${await res.text()}`);
  }
  return res.json();
}

function uniqueFullNamesFromText(text) {
  const names = [];
  const seen = new Set();
  const patterns = [
    /href=["']\/([A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+)(?:["'#?\/])/g,
    /https:\/\/github\.com\/([A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+)/g,
    /\b([A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+)\b/g,
  ];
  for (const pattern of patterns) {
    let match;
    while ((match = pattern.exec(text)) !== null) {
      const fullName = match[1];
      if (!looksLikeGithubFullName(fullName)) continue;
      const owner = fullName.split("/")[0].toLowerCase();
      if (bannedPseudoOwners().includes(owner)) continue;
      if (!seen.has(fullName)) {
        seen.add(fullName);
        names.push(fullName);
      }
    }
  }
  return names;
}

function bannedPseudoOwners() {
  return [
    "_next",
    "apps",
    "application",
    "blog",
    "collections",
    "components",
    "css",
    "customer-stories",
    "enterprise",
    "explore",
    "features",
    "fonts.googleapis.com",
    "marketplace",
    "orgs",
    "out",
    "repo",
    "repositories",
    "resources",
    "solutions",
    "sponsors",
    "topics",
    "trending",
    "www.googletagmanager.com",
  ];
}

function looksLikeGithubFullName(fullName) {
  if (typeof fullName !== "string" || !/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/.test(fullName)) {
    return false;
  }
  const [owner, repo] = fullName.split("/");
  if (!owner || !repo || owner.includes(".") || owner.length > 39) return false;
  if (repo.endsWith(".css") || repo.endsWith(".html") || repo.endsWith(".png") || repo.endsWith(".xml")) return false;
  if (/^\d+$/.test(owner) || /^\d+$/.test(repo)) return false;
  return true;
}

async function repoDetails(fullName) {
  const repo = await githubJson(`https://api.github.com/repos/${fullName}`);
  if (repo.archived || repo.disabled || repo.fork) return null;
  return repo;
}

async function hydrateCandidates(fullNames, platform, tag, limit) {
  const bucket = [];
  let rank = 0;
  let attempts = 0;
  for (const fullName of fullNames) {
    if (bucket.length >= limit || attempts >= limit * 8) break;
    attempts++;
    try {
      const repo = await repoDetails(fullName);
      if (!repo) continue;
      rank++;
      bucket.push({
        ...repo,
        source_platform: platform,
        source_tag: tag,
        source_rank: rank,
        source_score: scoreRepo(repo),
      });
    } catch (error) {
      console.warn(`Hydrate failed ${fullName}: ${error.message}`);
    }
  }
  return bucket;
}

async function githubTrendingBucket(config) {
  const since = periodToSince();
  const text = await fetchText(`https://github.com/trending?since=${encodeURIComponent(since)}`);
  return hydrateCandidates(uniqueFullNamesFromText(text), "github_trending", since === "weekly" ? "weekly" : "daily", config.per_page);
}

async function ossInsightBucket(config) {
  const period = periodType === "weekly" ? "past_7_days" : "past_24_hours";
  const url = `https://api.ossinsight.io/v1/trends/repos/?period=${period}&language=All`;
  const res = await fetch(url, { headers: { "Accept": "application/json", "User-Agent": "GIR-Discover" } });
  if (!res.ok) {
    throw new Error(`OSSInsight ${res.status}: ${await res.text()}`);
  }
  const json = await res.json();
  const rows = json?.data?.rows || json?.data || json?.repos || json?.items || [];
  const fullNames = [];
  for (const row of rows) {
    const fullName = row.repo_name || row.full_name || row.repo || row.repository || row.name;
    if (typeof fullName === "string" && fullName.includes("/")) fullNames.push(fullName);
  }
  return hydrateCandidates(fullNames, "ossinsight", periodType === "weekly" ? "weekly" : "daily", config.per_page);
}

async function trendshiftBucket(config) {
  const text = await fetchText("https://trendshift.io/");
  return hydrateCandidates(uniqueFullNamesFromText(text), "trendshift", periodType === "weekly" ? "weekly" : "daily", config.per_page);
}

async function repoRankBucket(config) {
  const text = await fetchText("https://reporank.co/");
  return hydrateCandidates(uniqueFullNamesFromText(text), "reporank", "momentum", config.per_page);
}

async function gitRepoTrendBucket(config) {
  try {
    const json = await fetchJson("https://gitrepotrend.com/api/init");
    const buckets = [];
    const seen = new Set();
    for (const [tag, repos] of Object.entries(json?.data || {})) {
      if (!Array.isArray(repos) || !repos.length) continue;
      const bucket = [];
      let rank = 0;
      for (const row of repos) {
        if (bucket.length >= config.per_page) break;
        const fullName = row.fullName || row.full_name;
        if (!looksLikeGithubFullName(fullName) || seen.has(fullName)) continue;
        seen.add(fullName);
        rank++;
        const sourceScore = Number(row.attentionRaw || row.attentionScore || 0);
        bucket.push({
          id: Number(row.id) || row.id,
          name: row.name || fullName.split("/")[1],
          full_name: fullName,
          html_url: row.url || `https://github.com/${fullName}`,
          description: row.description || "",
          stargazers_count: Number(row.stars || row.stargazers_count || 0),
          forks_count: Number(row.forks || row.forks_count || 0),
          watchers_count: Number(row.watchers || row.watchers_count || row.stars || 0),
          open_issues_count: Number(row.openIssues || row.open_issues_count || 0),
          language: row.language || "",
          topics: Array.isArray(row.topics) ? row.topics : [],
          pushed_at: row.updatedAt || row.updated_at || null,
          created_at: row.createdAt || row.created_at || null,
          archived: false,
          disabled: false,
          fork: Boolean(row.isFork),
          source_platform: "gitrepotrend",
          source_tag: tag,
          source_rank: rank,
          source_score: sourceScore || scoreRepo({
            pushed_at: row.updatedAt || row.updated_at,
            stargazers_count: row.stars,
            forks_count: row.forks,
          }),
        });
      }
      if (bucket.length) buckets.push(bucket);
    }
    if (buckets.length) return buckets;
  } catch (error) {
    console.warn(`GitRepoTrend API failed: ${error.message}; falling back to page scan`);
  }
  const text = await fetchText("https://gitrepotrend.com/");
  return hydrateCandidates(uniqueFullNamesFromText(text), "gitrepotrend", "attention", config.per_page);
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

async function githubSearchBuckets(config) {
  const specs = buildQuerySpecs(config);
  const buckets = [];
  for (const spec of specs) {
    const url = new URL("https://api.github.com/search/repositories");
    url.searchParams.set("q", spec.query);
    url.searchParams.set("sort", "stars");
    url.searchParams.set("order", "desc");
    url.searchParams.set("per_page", String(config.per_page));
    const data = await githubJson(url.toString());
    const bucket = [];
    let queryRank = 0;
    for (const repo of data.items || []) {
      if (repo.archived || repo.disabled || repo.fork) continue;
      queryRank++;
      const sourceScore = scoreRepo(repo);
      bucket.push({
        ...repo,
        source_platform: spec.platform,
        source_tag: spec.tag,
        source_rank: queryRank,
        source_score: sourceScore,
      });
    }
    buckets.push(bucket);
  }
  return buckets;
}

async function sourceBuckets(config) {
  const loaders = [
    ["github_trending", githubTrendingBucket],
    ["ossinsight", ossInsightBucket],
    ["trendshift", trendshiftBucket],
    ["reporank", repoRankBucket],
    ["gitrepotrend", gitRepoTrendBucket],
    ["github_search", githubSearchBuckets],
  ];
  const buckets = [];
  for (const [platform, loader] of loaders) {
    if (!platformEnabled(config, platform)) continue;
    try {
      const loaded = await loader(config);
      if (Array.isArray(loaded[0])) {
        buckets.push(...loaded.filter((bucket) => bucket.length));
      } else if (loaded.length) {
        buckets.push(loaded);
      }
      console.log(`Loaded ${platform}: ${Array.isArray(loaded[0]) ? loaded.reduce((sum, bucket) => sum + bucket.length, 0) : loaded.length}`);
    } catch (error) {
      console.warn(`Source failed ${platform}: ${error.message}`);
    }
  }
  return buckets;
}

async function searchProjects(config) {
  const buckets = await sourceBuckets(config);

  const selected = [];
  const seen = new Set();
  const consumed = new Map();

  for (const bucket of buckets) {
    for (let index = 0; index < bucket.length; index++) {
      const repo = bucket[index];
      if (!repo || seen.has(repo.full_name)) continue;
      seen.add(repo.full_name);
      selected.push(repo);
      consumed.set(bucket, index + 1);
      break;
    }
    if (selected.length >= config.max_projects) break;
  }

  for (let rank = 0; selected.length < config.max_projects && rank < config.per_page; rank++) {
    for (const bucket of buckets) {
      const offset = consumed.get(bucket) || 0;
      const repo = bucket[rank + offset];
      if (!repo || seen.has(repo.full_name)) continue;
      seen.add(repo.full_name);
      selected.push(repo);
      if (selected.length >= config.max_projects) break;
    }
  }

  if (selected.length < config.max_projects) {
    const remaining = buckets.flat()
      .filter((repo) => !seen.has(repo.full_name))
      .sort((a, b) => scoreRepo(b) - scoreRepo(a));
    for (const repo of remaining) {
      selected.push(repo);
      seen.add(repo.full_name);
      if (selected.length >= config.max_projects) break;
    }
  }

  return selected.map((repo, index) => ({
    ...repo,
    source_rank: repo.source_rank || index + 1,
    source_score: repo.source_score || scoreRepo(repo),
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
  console.log(`Discover config: max=${config.max_projects}, per_page=${config.per_page}, platforms=${config.platforms.join(",")}, topics=${config.topics.join(",")}`);
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
