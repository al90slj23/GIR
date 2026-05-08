const runType = process.env.RUN_TYPE || process.argv[2] || "daily";
const periodType = runType === "weekly" ? "weekly" : runType === "manual" ? "manual" : "daily";
const maxProjects = Number(process.env.MAX_PROJECTS || "10");

const env = {
  githubToken: process.env.GITHUB_TOKEN || "",
  deepseekKey: process.env.DEEPSEEK_API_KEY || "",
  deepseekBaseUrl: process.env.DEEPSEEK_BASE_URL || "https://api.deepseek.com",
  deepseekModel: process.env.DEEPSEEK_MODEL || "deepseek-chat",
  ingestUrl: process.env.APP_INGEST_URL || "",
  ingestToken: process.env.APP_INGEST_TOKEN || "",
};

for (const [key, value] of Object.entries(env)) {
  if (!value && ["deepseekKey", "ingestUrl", "ingestToken"].includes(key)) {
    throw new Error(`Missing required environment: ${key}`);
  }
}

const today = new Date().toISOString().slice(0, 10);
const since = new Date(Date.now() - (periodType === "weekly" ? 14 : 3) * 86400000)
  .toISOString()
  .slice(0, 10);

const queries = [
  `stars:>100 pushed:>${since}`,
  `created:>${since} stars:>20`,
  `topic:ai pushed:>${since} stars:>50`,
  `topic:llm pushed:>${since} stars:>50`,
  `topic:agent pushed:>${since} stars:>30`,
];

const ghHeaders = {
  "Accept": "application/vnd.github+json",
  "User-Agent": "AI-Project-Detective",
  ...(env.githubToken ? { "Authorization": `Bearer ${env.githubToken}` } : {}),
};

async function githubJson(url) {
  const res = await fetch(url, { headers: ghHeaders });
  if (!res.ok) {
    throw new Error(`GitHub API ${res.status}: ${await res.text()}`);
  }
  return res.json();
}

async function searchProjects() {
  const map = new Map();
  for (const q of queries) {
    const url = new URL("https://api.github.com/search/repositories");
    url.searchParams.set("q", q);
    url.searchParams.set("sort", "stars");
    url.searchParams.set("order", "desc");
    url.searchParams.set("per_page", "20");
    const data = await githubJson(url.toString());
    for (const repo of data.items || []) {
      if (repo.archived || repo.disabled || repo.fork) continue;
      if (!map.has(repo.full_name)) {
        map.set(repo.full_name, repo);
      }
    }
  }
  return [...map.values()]
    .sort((a, b) => scoreRepo(b) - scoreRepo(a))
    .slice(0, maxProjects);
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
    const res = await fetch(data.download_url, { headers: { "User-Agent": "AI-Project-Detective" } });
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

async function ingest(projects) {
  const payload = {
    run_type: runType,
    period_type: periodType,
    report_date: today,
    projects,
  };
  const res = await fetch(env.ingestUrl, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "Authorization": `Bearer ${env.ingestToken}`,
    },
    body: JSON.stringify(payload),
  });
  const text = await res.text();
  if (!res.ok) {
    throw new Error(`Ingest ${res.status}: ${text}`);
  }
  console.log(text);
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
    analysis,
  };
}

async function main() {
  const repos = await searchProjects();
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
