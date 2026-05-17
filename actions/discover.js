import { createHash } from "node:crypto";

const backfillMode = ["1", "true", "yes", "on"].includes(String(process.env.BACKFILL_EXISTING || "").toLowerCase());
const backlogPendingOnly = ["1", "true", "yes", "on"].includes(String(process.env.BACKLOG_PENDING_ONLY || "").toLowerCase());
const resetAnalyses = ["1", "true", "yes", "on"].includes(String(process.env.RESET_ANALYSES || "").toLowerCase());
const readmeFetchLimit = Math.max(0, Number(process.env.README_FETCH_LIMIT || "10"));
const readmeMaxBytes = Math.max(2000, Number(process.env.README_MAX_BYTES || "40000"));
let readmeFetchEnabled = true;
let readmeFetchBudget = readmeFetchLimit;
const runType = process.env.RUN_TYPE || process.argv[2] || "daily";
const periodType = backfillMode
  ? (backlogPendingOnly ? "manual" : "daily")
  : (runType === "weekly" ? "weekly" : (runType === "manual" || runType === "backlog") ? "manual" : "daily");
const ingestBatchSize = Math.max(1, Number(process.env.INGEST_BATCH_SIZE || "1"));
const rawIngestBatchSize = Math.max(1, Number(process.env.RAW_INGEST_BATCH_SIZE || "50"));
const analysisIngestBatchSize = Math.max(1, Number(process.env.ANALYSIS_INGEST_BATCH_SIZE || "20"));
const backfillFetchBatchSize = Math.max(1, Math.min(200, Number(process.env.BACKFILL_FETCH_BATCH_SIZE || "100")));
const backfillOffset = Math.max(0, Number(process.env.BACKFILL_OFFSET || "0"));
const backfillLimit = Math.max(0, Number(process.env.BACKFILL_LIMIT || "0"));
const requestDelayMs = Math.max(0, Number(process.env.REQUEST_DELAY_MS || "0"));
const ingestDelayMs = Math.max(0, Number(process.env.INGEST_DELAY_MS || "0"));
const githubSearchDelayMs = Math.max(0, Number(process.env.GITHUB_SEARCH_DELAY_MS || "3500"));
const githubRateLimitRetries = Math.max(0, Number(process.env.GITHUB_RATE_LIMIT_RETRIES || "2"));
const githubMaxRateLimitWaitMs = Math.max(0, Number(process.env.GITHUB_MAX_RATE_LIMIT_WAIT_MS || "60000"));
const githubSearchSpecOffset = Math.max(0, Number(process.env.GITHUB_SEARCH_SPEC_OFFSET || "0"));
const githubSearchSpecLimit = Math.max(0, Number(process.env.GITHUB_SEARCH_SPEC_LIMIT || "0"));
const seedMode = ["1", "true", "yes", "on"].includes(String(process.env.SEED_MODE || "").toLowerCase());
const reportTimezone = process.env.REPORT_TIMEZONE || "Asia/Shanghai";

const env = {
  githubToken: process.env.GIR_GITHUB_SEARCH_TOKEN || process.env.GH_PAT || process.env.GITHUB_TOKEN || "",
  deepseekKey: process.env.DEEPSEEK_API_KEY || "",
  deepseekBaseUrl: process.env.DEEPSEEK_BASE_URL || "https://api.deepseek.com",
  deepseekModel: process.env.DEEPSEEK_MODEL || "deepseek-chat",
  ingestUrl: process.env.APP_INGEST_URL || "",
  ingestToken: process.env.APP_INGEST_TOKEN || "",
  configUrl: process.env.APP_CONFIG_URL || "",
  platformOverride: process.env.DISCOVER_PLATFORMS || "",
  githubSearchQuery: process.env.DISCOVER_EXTRA_QUERY || "",
};

for (const [key, value] of Object.entries(env)) {
  if (!value && ["deepseekKey", "ingestUrl", "ingestToken"].includes(key)) {
    throw new Error(`Missing required environment: ${key}`);
  }
}

const defaultConfig = {
  backlog_enabled: true,
  backlog_batch_size: 40,
  analyze_all: true,
  max_projects: Number(process.env.MAX_PROJECTS || "10"),
  per_page: 20,
  recent_days_daily: 3,
  recent_days_weekly: 14,
  min_stars_general: 100,
  min_stars_created: 20,
  min_stars_topic: 50,
  min_stars_agent: 30,
  topics: ["ai", "llm", "agent", "php"],
  extra_queries: [],
  include_default_search_specs: true,
  platforms: ["github_trending", "ossinsight", "trendshift", "reporank", "gitrepotrend"],
  deepseek_system_prompt: defaultSystemPrompt(),
  deepseek_task_prompt: defaultTaskPrompt(),
};

const trendshiftFallbackTopics = [
  "ai-agent",
  "ai-coding-assistant",
  "ai-skills",
  "self-hosted",
  "curated-list",
  "ai-workflow",
  "workflow-automation",
  "mcp",
  "document-processing",
  "rag",
  "web-scraping",
  "ai-infrastructure",
  "local-llm",
  "fintech",
  "programming-examples",
  "data-visualization",
  "developer-tools",
  "security",
  "database",
  "game-development",
];

const seedTopics = [
  "ai",
  "llm",
  "agent",
  "machine-learning",
  "deep-learning",
  "generative-ai",
  "rag",
  "chatgpt",
  "php",
  "laravel",
  "wordpress",
  "javascript",
  "typescript",
  "react",
  "vue",
  "nextjs",
  "python",
  "go",
  "rust",
  "java",
  "devtools",
  "cli",
  "automation",
  "productivity",
  "self-hosted",
  "cms",
  "dashboard",
  "monitoring",
  "security",
  "data-visualization",
  "crawler",
  "web-scraping",
  "api",
  "database",
  "sqlite",
  "mysql",
  "game",
  "education",
  "finance",
  "web3",
];

const seedExtraQueries = [
  "language:PHP stars:>0 pushed:>{since}",
  "language:JavaScript stars:>50 pushed:>{since}",
  "language:TypeScript stars:>50 pushed:>{since}",
  "language:Python stars:>50 pushed:>{since}",
  "language:Go stars:>50 pushed:>{since}",
  "language:Rust stars:>50 pushed:>{since}",
  "topic:self-hosted stars:>20 pushed:>{since}",
  "topic:dashboard stars:>20 pushed:>{since}",
  "topic:cms stars:>20 pushed:>{since}",
  "topic:admin-panel stars:>20 pushed:>{since}",
  "topic:automation stars:>20 pushed:>{since}",
  "topic:ai-agent stars:>10 pushed:>{since}",
  "topic:rag stars:>10 pushed:>{since}",
  "topic:developer-tools stars:>20 pushed:>{since}",
  "topic:web-scraping stars:>20 pushed:>{since}",
  "topic:data-visualization stars:>20 pushed:>{since}",
  "topic:php stars:>0 created:>{since}",
  "topic:ai stars:>0 created:>{since}",
  "topic:agent stars:>0 created:>{since}",
  "topic:llm stars:>0 created:>{since}",
];

const today = process.env.REPORT_DATE || dateInTimeZone(new Date(), reportTimezone);

function dateInTimeZone(date, timeZone) {
  const parts = new Intl.DateTimeFormat("en-CA", {
    timeZone,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).formatToParts(date);
  const values = Object.fromEntries(parts.map((part) => [part.type, part.value]));
  return `${values.year}-${values.month}-${values.day}`;
}

function githubHeaders(token = env.githubToken) {
  return {
    "Accept": "application/vnd.github+json",
    "User-Agent": "GIR-Discover",
    ...(token ? { "Authorization": `Bearer ${token}` } : {}),
  };
}

const ghHeaders = githubHeaders();

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

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

function deriveHistoryUrl() {
  if (/\/api\/receive_projects\.php($|\?)/.test(env.ingestUrl)) {
    return env.ingestUrl.replace(/\/api\/receive_projects\.php($|\?.*)/, "/api/project_history.php");
  }
  return "";
}

function deriveBackfillProjectsUrl() {
  if (/\/api\/receive_projects\.php($|\?)/.test(env.ingestUrl)) {
    return env.ingestUrl.replace(/\/api\/receive_projects\.php($|\?.*)/, "/api/backfill_projects.php");
  }
  return "";
}

function deriveResetAnalysesUrl() {
  if (/\/api\/receive_projects\.php($|\?)/.test(env.ingestUrl)) {
    return env.ingestUrl.replace(/\/api\/receive_projects\.php($|\?.*)/, "/api/reset_analyses.php");
  }
  return "";
}

function deriveReadmeQueueUrl() {
  if (/\/api\/receive_projects\.php($|\?)/.test(env.ingestUrl)) {
    return env.ingestUrl.replace(/\/api\/receive_projects\.php($|\?.*)/, "/api/readme_queue.php");
  }
  return "";
}

function deriveReceiveReadmesUrl() {
  if (/\/api\/receive_projects\.php($|\?)/.test(env.ingestUrl)) {
    return env.ingestUrl.replace(/\/api\/receive_projects\.php($|\?.*)/, "/api/receive_readmes.php");
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
    const platforms = normalizeList(env.platformOverride).length
      ? normalizeList(env.platformOverride)
      : (normalizeList(config.platforms).length ? normalizeList(config.platforms) : defaultConfig.platforms);
    return {
      daily_enabled: config.daily_enabled !== false,
      weekly_enabled: config.weekly_enabled !== false,
      backlog_enabled: config.backlog_enabled !== false,
      backlog_batch_size: clampNumber(config.backlog_batch_size, defaultConfig.backlog_batch_size, 1, 200),
      analyze_all: config.analyze_all !== false,
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
      include_default_search_specs: config.include_default_search_specs !== false,
      platforms,
      deepseek_system_prompt: String(config.deepseek_system_prompt || defaultConfig.deepseek_system_prompt).trim() || defaultConfig.deepseek_system_prompt,
      deepseek_task_prompt: String(config.deepseek_task_prompt || defaultConfig.deepseek_task_prompt).trim() || defaultConfig.deepseek_task_prompt,
      deepseek_api_key: String(config.deepseek_api_key || "").trim(),
      deepseek_base_url: String(config.deepseek_base_url || "").trim(),
      deepseek_model: String(config.deepseek_model || "").trim(),
      analyze_concurrency: clampNumber(config.analyze_concurrency, 10, 1, 200),
      readme_fetch_enabled: config.readme_fetch_enabled !== false,
      readme_per_run: clampNumber(config.readme_per_run, 10, 0, 200),
    };
  } catch (error) {
    console.warn(`Config error: ${error.message}; using defaults`);
    return defaultConfig;
  }
}

function platformEnabled(config, platform) {
  return config.platforms.includes(platform);
}

function applySeedConfig(config) {
  if (!seedMode) return config;
  return {
    ...config,
    analyze_all: true,
    per_page: 100,
    recent_days_daily: 365,
    recent_days_weekly: 365,
    min_stars_general: 0,
    min_stars_created: 0,
    min_stars_topic: 0,
    min_stars_agent: 0,
    topics: seedTopics,
    extra_queries: [...new Set([...config.extra_queries, ...seedExtraQueries])],
    include_default_search_specs: true,
  };
}

function applyDynamicSearchConfig(config) {
  const query = String(env.githubSearchQuery || "").trim();
  if (!query) return config;
  return {
    ...config,
    analyze_all: true,
    platforms: ["github_search"],
    topics: [],
    extra_queries: [query],
    include_default_search_specs: false,
  };
}

function applyRuntimeOverrides(config) {
  const platforms = normalizeList(env.platformOverride);
  if (!platforms.length) return config;
  return { ...config, platforms };
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

async function fetchWithTimeout(url, options = {}, timeoutMs = 90000) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), timeoutMs);
  try {
    return await fetch(url, { ...options, signal: controller.signal });
  } finally {
    clearTimeout(timeout);
  }
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
  const buckets = [];
  const homeText = await fetchText("https://trendshift.io/");
  const homeBucket = await hydrateCandidates(uniqueFullNamesFromText(homeText), "trendshift", periodType === "weekly" ? "weekly" : "daily", config.per_page);
  if (homeBucket.length) buckets.push(homeBucket);

  const extraPages = [
    ["github-trending", "https://trendshift.io/github-trending-repositories"],
    ["repository-engagements", "https://trendshift.io/repository-engagements"],
  ];
  for (const [tag, url] of extraPages) {
    try {
      if (requestDelayMs > 0) await sleep(requestDelayMs);
      const text = await fetchText(url);
      const bucket = await hydrateCandidates(uniqueFullNamesFromText(text), "trendshift", tag, config.per_page);
      if (bucket.length) buckets.push(bucket);
    } catch (error) {
      console.warn(`Trendshift page failed ${tag}: ${error.message}`);
    }
  }

  let topics = [];
  try {
    if (requestDelayMs > 0) await sleep(requestDelayMs);
    const topicIndex = await fetchText("https://trendshift.io/topics");
    topics = Array.from(topicIndex.matchAll(/href=["']\/topics\/([a-z0-9-]+)["']/gi))
      .map((match) => match[1])
      .filter((slug) => slug && slug !== "topics");
  } catch (error) {
    console.warn(`Trendshift topics index failed: ${error.message}; using fallback topics`);
  }
  topics = [...new Set([...topics, ...trendshiftFallbackTopics])].slice(0, 60);

  for (const topic of topics) {
    try {
      if (requestDelayMs > 0) await sleep(requestDelayMs);
      const text = await fetchText(`https://trendshift.io/topics/${encodeURIComponent(topic)}`);
      const bucket = await hydrateCandidates(uniqueFullNamesFromText(text), "trendshift", topic, config.per_page);
      if (bucket.length) buckets.push(bucket);
    } catch (error) {
      console.warn(`Trendshift topic failed ${topic}: ${error.message}`);
    }
  }

  return buckets;
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
  const specs = [];
  if (config.include_default_search_specs !== false) {
    specs.push(
      { platform: "github_search", tag: "综合", query: `stars:>${config.min_stars_general} pushed:>${since}` },
      { platform: "github_search", tag: "新项目", query: `created:>${since} stars:>${config.min_stars_created}` },
    );
    for (const topic of config.topics) {
      const minStars = topic.toLowerCase() === "agent" ? config.min_stars_agent : config.min_stars_topic;
      specs.push({ platform: "github_search", tag: topic, query: `topic:${topic} pushed:>${since} stars:>${minStars}` });
    }
  }
  for (const [index, query] of config.extra_queries.entries()) {
    const resolvedQuery = query.replaceAll("{since}", since);
    specs.push({
      platform: "github_search",
      tag: config.include_default_search_specs === false ? `搜索:${resolvedQuery.slice(0, 52)}` : `自定义${index + 1}`,
      query: resolvedQuery,
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

function githubRateLimitWaitMs(res, text) {
  const retryAfter = Number(res.headers.get("retry-after") || "0");
  if (Number.isFinite(retryAfter) && retryAfter > 0) {
    return retryAfter * 1000;
  }

  const remaining = Number(res.headers.get("x-ratelimit-remaining") || "NaN");
  const reset = Number(res.headers.get("x-ratelimit-reset") || "0");
  if (remaining === 0 && reset > 0) {
    return Math.max(0, reset * 1000 - Date.now() + 2500);
  }

  if (/rate limit|secondary rate|too many requests/i.test(text)) {
    return 30000;
  }
  return 0;
}

async function githubJson(url, options = {}) {
  const retries = Number.isFinite(options.retries) ? options.retries : githubRateLimitRetries;
  for (let attempt = 0; attempt <= retries; attempt++) {
    const res = await fetch(url, { headers: options.headers || ghHeaders });
    if (res.ok) {
      return res.json();
    }

    const text = await res.text();
    const waitMs = githubRateLimitWaitMs(res, text);
    if ((res.status === 403 || res.status === 429) && waitMs > 0 && waitMs <= githubMaxRateLimitWaitMs && attempt < retries) {
      console.warn(`GitHub API ${res.status}: rate limited, waiting ${Math.ceil(waitMs / 1000)}s before retry ${attempt + 1}/${retries}`);
      await sleep(waitMs);
      continue;
    }

    const error = new Error(`GitHub API ${res.status}: ${text}`);
    error.status = res.status;
    error.rateLimited = waitMs > 0;
    throw error;
  }
  throw new Error(`GitHub API failed: ${url}`);
}

async function githubSearchBuckets(config) {
  const specs = buildQuerySpecs(config);
  const scopedSpecs = githubSearchSpecLimit > 0
    ? specs.slice(githubSearchSpecOffset, githubSearchSpecOffset + githubSearchSpecLimit)
    : specs.slice(githubSearchSpecOffset);
  const buckets = [];
  console.log(`GitHub search specs: total=${specs.length}, offset=${githubSearchSpecOffset}, limit=${githubSearchSpecLimit || "all"}, running=${scopedSpecs.length}`);
  for (const [index, spec] of scopedSpecs.entries()) {
    const url = new URL("https://api.github.com/search/repositories");
    url.searchParams.set("q", spec.query);
    url.searchParams.set("sort", "stars");
    url.searchParams.set("order", "desc");
    url.searchParams.set("per_page", String(config.per_page));
    let data;
    try {
      data = await githubJson(url.toString());
    } catch (error) {
      if (error.rateLimited) {
        console.warn(`GitHub search rate limited at spec ${githubSearchSpecOffset + index + 1}/${specs.length}; returning ${buckets.length} buckets already loaded`);
        break;
      }
      console.warn(`GitHub search failed ${spec.tag}: ${error.message}`);
      continue;
    }
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
    if (githubSearchDelayMs > 0 && index + 1 < scopedSpecs.length) {
      await sleep(githubSearchDelayMs);
    }
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

function selectProjectsFromBuckets(buckets, config) {
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

function rankingCandidatesFromBuckets(buckets) {
  const candidates = [];
  const seen = new Set();
  for (const bucket of buckets) {
    for (const repo of bucket) {
      if (!repo?.full_name) continue;
      const key = `${repo.source_platform || "github"}\n${repo.source_tag || "综合"}\n${repo.full_name}`;
      if (seen.has(key)) continue;
      seen.add(key);
      candidates.push({
        ...repo,
        source_score: repo.source_score || scoreRepo(repo),
      });
    }
  }
  return candidates;
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

function defaultSystemPrompt() {
  return [
    "你是一个帮助站长发现 GitHub 新项目的技术分析员。",
    "你的目标是把项目讲成人能快速理解的中文：它做什么、为什么值得关注、适合谁、怎么用、有什么可借鉴点；解读要**充分、具体、有信息量**，不要只写一句话。",
    "不要把本站运行环境或某个特定技术栈当作通用评价标准；除非输入明确要求，否则不要把部署条件作为主要结论。",
    "你必须用中文输出严格 JSON，不要 Markdown，不要解释。",
    "评分为 1 到 10 的整数。",
    "play_score 衡量项目是否有趣、是否值得点开体验、是否能带来灵感。",
    "useful_score 衡量项目是否解决真实问题、是否有明确使用价值。",
    "maturity_score 衡量项目成熟度，综合 Stars、Forks、最近更新、文档完整度和社区活跃度。",
    "difficulty 衡量理解、部署、改造或复刻成本，只能输出 低、中、高。",
    "如果输入里有 previous_analyses（历史解说）或 repo_deltas（数值变化），必须在 change_observation 字段里认真对比并给出观察；否则 has_previous 置 false。",
  ].join("\n");
}

function defaultTaskPrompt() {
  return [
    "请围绕这个项目生成一份**完整、结构化、内容充实**的中文解读，分两层：",
    "",
    "1. change_observation：时间序列对比",
    "   - 如果 previous_analyses 或 repo_deltas 里提供了上一次解读和数值（stars/forks 增长、跨度天数），必须据此写出：",
    "     * 热度变化（涨势/跌势/稳定，配合绝对数字）",
    "     * 定位或功能上的变化（从上次解读到现在是否新增了功能板块、重大版本、架构调整、README 新章节等）",
    "     * 这次是否值得重新关注的理由",
    "   - 如果没有 previous_analyses（首次解读），把 has_previous 置 false，其他字段给合理默认值或空字符串。",
    "",
    "2. project_profile：项目画像（详细版本）",
    "   - what_it_does：项目做什么，3 到 5 句话，不要一句带过",
    "   - who_it_is_for：具体谁会用，用什么场景",
    "   - architecture_or_stack：技术栈和关键架构特点",
    "   - standout_features：3 到 6 条值得关注的功能、特色或亮点",
    "   - typical_workflow：用户从零到用起来的典型上手流程或用法示例",
    "   - risks_and_caveats：可能踩坑的地方、依赖门槛、license 风险、社区活跃度等",
    "",
    "表达要说人话，避免空泛夸奖。不要默认围绕部署条件评价项目，也不要因为项目依赖较多或运行门槛较高就直接给出\"暂不关注\"；只有当部署门槛会明显影响目标用户采用时，才在 risks_and_caveats 里简短说明。",
  ].join("\n");
}

function systemPrompt(config) {
  return String(config.deepseek_system_prompt || defaultSystemPrompt()).trim() || defaultSystemPrompt();
}

function compactHistory(history) {
  return history.map((item) => ({
    date: item.report_date,
    source: `${item.source_platform || ""}/${item.source_tag || ""}`,
    one_sentence: item.one_sentence || "",
    change_note: item.change_note || "",
    summary: item.summary_zh || "",
    recommendation: item.recommendation || "",
    scores: {
      play: Number(item.play_score || 0),
      useful: Number(item.useful_score || 0),
      maturity: Number(item.maturity_score || 0),
      difficulty: item.difficulty || "",
    },
  }));
}

function computeRepoDeltas(repo, history) {
  if (!history || !history.length) {
    return { has_previous: false };
  }
  const prev = history[0];
  let prevSnap = prev.repo_snapshot || null;
  if (!prevSnap && prev.raw_ai_json) {
    try {
      const parsed = JSON.parse(prev.raw_ai_json);
      if (parsed && parsed.repo_snapshot) prevSnap = parsed.repo_snapshot;
    } catch {}
  }
  const prevStars = prevSnap && typeof prevSnap.stars === "number" ? prevSnap.stars : null;
  const prevForks = prevSnap && typeof prevSnap.forks === "number" ? prevSnap.forks : null;
  const prevTime = prev.created_at ? Date.parse(prev.created_at) : null;
  const spanDays = prevTime ? Math.max(0, Math.floor((Date.now() - prevTime) / 86400000)) : null;
  return {
    has_previous: true,
    previous_report_date: prev.report_date || null,
    previous_created_at: prev.created_at || null,
    span_days: spanDays,
    star_growth: prevStars !== null ? (Number(repo.stargazers_count || 0) - prevStars) : null,
    fork_growth: prevForks !== null ? (Number(repo.forks_count || 0) - prevForks) : null,
    current_stars: Number(repo.stargazers_count || 0),
    current_forks: Number(repo.forks_count || 0),
  };
}

function userPrompt(repo, readme, history = [], config = defaultConfig, extras = {}) {
  return JSON.stringify({
    task: String(config.deepseek_task_prompt || defaultTaskPrompt()).trim() || defaultTaskPrompt(),
    required_schema: {
      one_sentence: "一句话介绍（保留，作为 header）",
      project_type: "工具/框架/游戏/Agent/UI/后端/数据集/其他",
      problem: "解决什么问题",
      tech_stack: ["主要技术栈"],
      target_users: ["适合谁用"],
      play_score: "1-10",
      useful_score: "1-10",
      maturity_score: "1-10，综合 Stars、Forks、最近更新、文档完整度、社区活跃度判断成熟度",
      difficulty: "低/中/高",
      ideas_to_reuse: ["可借鉴点"],
      risks: ["风险点"],
      change_note: "本次解读相对上次的总体变化一句话总结（没变化则说无明显变化）",
      change_observation: {
        has_previous: "true/false",
        trigger_reasons: ["列出本次重新解读的具体触发原因，参考 input.refresh_reasons；说人话，例如「Stars 增长 1234（从 3000 到 4234）」「README 内容变化」"],
        growth_intensity: "低/中/高（基于 repo_deltas.star_growth 和 span_days）",
        what_changed: "README 新章节 / 新功能板块 / 定位变化等具体描述",
        why_it_matters: "对用户决定是否重新关注的影响",
      },
      project_profile: {
        what_it_does: "3-5 句话详细说明",
        who_it_is_for: "具体谁会用，什么场景",
        architecture_or_stack: "技术栈和关键架构特点",
        standout_features: ["亮点 1", "亮点 2", "亮点 3"],
        typical_workflow: "从零到用起来的流程",
        risks_and_caveats: "依赖/license/活跃度等风险",
      },
      recommendation: "收藏/研究/可复刻/暂不关注",
      summary_zh: "中文总结（通用介绍段，可以比 one_sentence 长一些）",
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
    repo_snapshot: {
      stars: Number(repo.stargazers_count || 0),
      forks: Number(repo.forks_count || 0),
      pushed_at: repo.pushed_at || null,
    },
    repo_deltas: computeRepoDeltas(repo, history),
    refresh_reasons: Array.isArray(extras?.refresh_reasons) ? extras.refresh_reasons : [],
    previous_analyses: compactHistory(history).slice(0, 5),
    readme,
  });
}

async function fetchProjectHistory(fullName) {
  const url = deriveHistoryUrl();
  if (!url) return [];
  try {
    const res = await fetchWithTimeout(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Authorization": `Bearer ${env.ingestToken}`,
        "User-Agent": "GIR-Discover",
      },
      body: JSON.stringify({ full_name: fullName, limit: 5 }),
    }, 30000);
    if (!res.ok) {
      console.warn(`History ${fullName} ${res.status}: ${await res.text()}`);
      return [];
    }
    const data = await res.json();
    return Array.isArray(data.reports) ? data.reports : [];
  } catch (error) {
    console.warn(`History ${fullName} failed: ${error.message}`);
    return [];
  }
}

async function resetAnalysesOnHost() {
  const url = deriveResetAnalysesUrl();
  if (!url) {
    throw new Error("Cannot derive reset_analyses API URL from APP_INGEST_URL");
  }
  const form = new URLSearchParams();
  form.set("confirm", "clear_analyses");
  const res = await fetchWithTimeout(url, {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
      "Authorization": `Bearer ${env.ingestToken}`,
      "User-Agent": "GIR-Discover",
    },
    body: form.toString(),
  }, 60000);
  const text = await res.text();
  if (!res.ok) {
    throw new Error(`Reset analyses ${res.status}: ${text}`);
  }
  console.log(`Reset analyses: ${text}`);
}

async function fetchBackfillProjectsPage(offset, limit) {
  const baseUrl = deriveBackfillProjectsUrl();
  if (!baseUrl) {
    throw new Error("Cannot derive backfill_projects API URL from APP_INGEST_URL");
  }
  const url = new URL(baseUrl);
  url.searchParams.set("offset", String(offset));
  url.searchParams.set("limit", String(limit));
  if (backlogPendingOnly) {
    url.searchParams.set("pending_only", "1");
  }
  const maxAttempts = 4;
  let lastError = null;
  for (let attempt = 1; attempt <= maxAttempts; attempt++) {
    try {
      const res = await fetchWithTimeout(url.toString(), {
        headers: {
          "Accept": "application/json",
          "Authorization": `Bearer ${env.ingestToken}`,
          "User-Agent": "GIR-Discover",
        },
      }, 60000);
      if (!res.ok) {
        throw new Error(`Backfill projects ${res.status}: ${await res.text()}`);
      }
      return await res.json();
    } catch (error) {
      lastError = error;
      if (attempt >= maxAttempts) break;
      const waitMs = 3000 * attempt;
      console.warn(`Backfill projects attempt ${attempt}/${maxAttempts} failed: ${error?.message || error}; retrying in ${waitMs}ms`);
      await sleep(waitMs);
    }
  }
  throw lastError || new Error("Backfill projects failed");
}

function resolvedBackfillLimit(config) {
  if (backfillLimit > 0) {
    return backfillLimit;
  }
  if (backlogPendingOnly) {
    return Math.max(1, Number(config.backlog_batch_size || defaultConfig.backlog_batch_size));
  }
  return 0;
}

async function loadBackfillProjects(config) {
  const projects = [];
  let offset = backfillOffset;
  let total = null;
  const effectiveLimit = resolvedBackfillLimit(config);
  while (true) {
    if (effectiveLimit > 0 && projects.length >= effectiveLimit) break;
    const pageLimit = effectiveLimit > 0
      ? Math.min(backfillFetchBatchSize, effectiveLimit - projects.length)
      : backfillFetchBatchSize;
    const data = await fetchBackfillProjectsPage(offset, pageLimit);
    const pageProjects = Array.isArray(data.projects) ? data.projects : [];
    total = Number(data.total || total || 0);
    projects.push(...pageProjects);
    console.log(`Loaded backfill projects: offset=${offset}, count=${pageProjects.length}, total_loaded=${projects.length}, total=${total}`);
    if (pageProjects.length < pageLimit) break;
    offset += pageProjects.length;
  }
  return projects;
}

async function runBackfill(config) {
  console.log(`Backfill config: reset=${resetAnalyses}, offset=${backfillOffset}, limit=${resolvedBackfillLimit(config) || "all"}, pending_only=${backlogPendingOnly}, report_date=${today}`);
  if (resetAnalyses) {
    await resetAnalysesOnHost();
  }

  const repos = await loadBackfillProjects(config);
  if (!repos.length) {
    if (backlogPendingOnly) {
      console.log("Backlog clear: no pending projects");
      return;
    }
    throw new Error("No projects loaded for backfill");
  }

  if (!backlogPendingOnly) {
    console.log(`Ingesting backfill raw candidates: ${repos.length}`);
    await ingest(repos.map((repo) => projectPayload(repo, { raw_rank_only: true })), rawIngestBatchSize);
  }

  let analyzedCount = 0;
  const analyzedBatch = [];
  const concurrency = Math.max(1, Math.min(200, Number(process.env.ANALYZE_CONCURRENCY || "10")));
  const flushLock = { running: false };
  const flushIfFull = async () => {
    if (flushLock.running) return;
    if (analyzedBatch.length < analysisIngestBatchSize) return;
    flushLock.running = true;
    try {
      const slice = analyzedBatch.splice(0, analysisIngestBatchSize);
      await ingest(slice, analysisIngestBatchSize);
    } finally {
      flushLock.running = false;
    }
  };

  const repoIter = repos[Symbol.iterator]();
  const workers = [];
  for (let i = 0; i < Math.min(concurrency, repos.length); i++) {
    workers.push((async () => {
      while (true) {
        const next = repoIter.next();
        if (next.done) return;
        const repo = next.value;
        console.log(`Backfill analyzing ${repo.full_name}`);
        try {
          const history = await fetchProjectHistory(repo.full_name);
          const readme = await getReadme(repo);
          const analysis = await analyze(repo, readme, history, config);
          analyzedBatch.push(projectPayload(repo, analysis));
          analyzedCount++;
          await flushIfFull();
        } catch (error) {
          console.error(`Backfill failed ${repo.full_name}: ${error.message}`);
        }
      }
    })());
  }
  await Promise.all(workers);

  if (analyzedBatch.length) {
    await ingest(analyzedBatch, analysisIngestBatchSize);
  }
  if (!analyzedCount && !backlogPendingOnly) {
    throw new Error("No projects analyzed in backfill");
  }
  console.log(`Backfill complete: total_loaded=${repos.length}, total_analyzed=${analyzedCount}`);
}

function decodeBase64Utf8(value) {
  return Buffer.from(String(value || ""), "base64").toString("utf8");
}

function truncateReadme(text) {
  if (!text) return "";
  if (Buffer.byteLength(text, "utf8") <= readmeMaxBytes) return text;
  const slice = Buffer.from(text, "utf8").subarray(0, readmeMaxBytes).toString("utf8");
  return slice;
}

function looksChinese(text) {
  if (!text) return false;
  const letters = (text.match(/[\p{L}]/gu) || []).length;
  if (letters === 0) return false;
  const cjk = (text.match(/[\u4E00-\u9FFF\u3400-\u4DBF]/g) || []).length;
  return cjk / letters >= 0.2;
}

function looksLikeReadmeFilename(name) {
  if (typeof name !== "string") return false;
  // Match both README.zh-CN.md and README_CN.md / README-CN.md styles
  return /^readme([._\-][a-z0-9_\-]+)?\.(md|markdown|mdown|mkd|txt|rst)$/i.test(name);
}

function pickZhReadmeCandidate(name) {
  if (!looksLikeReadmeFilename(name)) return null;
  const lower = name.toLowerCase();
  if (/^readme[._\-](zh[-_]?cn|zh[-_]?tw|cn|zh|chs|cht)\./.test(lower)) return lower;
  return null;
}

async function fetchRepoContentsRoot(fullName) {
  try {
    return await githubJson(`https://api.github.com/repos/${fullName}/contents`);
  } catch (error) {
    console.warn(`Contents listing failed ${fullName}: ${error.message}`);
    return [];
  }
}

async function fetchReadmeAtPath(fullName, path) {
  try {
    const data = await githubJson(`https://api.github.com/repos/${fullName}/contents/${encodeURIComponent(path)}`);
    if (!data || !data.content) return null;
    const encoding = String(data.encoding || "base64").toLowerCase();
    const content = encoding === "base64" ? decodeBase64Utf8(data.content) : String(data.content || "");
    return {
      readme_path: String(data.path || path),
      source_url: String(data.html_url || `https://github.com/${fullName}/blob/HEAD/${path}`),
      content_md: truncateReadme(content),
    };
  } catch (error) {
    console.warn(`Readme path ${fullName}:${path} failed: ${error.message}`);
    return null;
  }
}

async function fetchDefaultReadme(fullName) {
  try {
    const data = await githubJson(`https://api.github.com/repos/${fullName}/readme`);
    if (!data || !data.content) return null;
    const encoding = String(data.encoding || "base64").toLowerCase();
    const content = encoding === "base64" ? decodeBase64Utf8(data.content) : String(data.content || "");
    return {
      readme_path: String(data.path || "README.md"),
      source_url: String(data.html_url || `https://github.com/${fullName}/blob/HEAD/README.md`),
      content_md: truncateReadme(content),
    };
  } catch (error) {
    console.warn(`Readme default ${fullName} failed: ${error.message}`);
    return null;
  }
}

async function collectProjectReadmes(fullName) {
  const readmes = [];
  const seenPaths = new Set();

  const defaultReadme = await fetchDefaultReadme(fullName);
  if (defaultReadme && defaultReadme.content_md) {
    const lang = looksChinese(defaultReadme.content_md) ? "zh" : "en";
    readmes.push({
      readme_path: defaultReadme.readme_path,
      language_code: lang,
      is_translated: false,
      source_url: defaultReadme.source_url,
      content_md: defaultReadme.content_md,
    });
    seenPaths.add(defaultReadme.readme_path.toLowerCase());
  }

  if (!readmes.some((item) => item.language_code === "zh" && !item.is_translated)) {
    const contents = await fetchRepoContentsRoot(fullName);
    const zhCandidates = [];
    if (Array.isArray(contents)) {
      for (const entry of contents) {
        if (!entry || entry.type !== "file") continue;
        const name = String(entry.name || "");
        if (pickZhReadmeCandidate(name) && !seenPaths.has(name.toLowerCase())) {
          zhCandidates.push(name);
        }
      }
    }
    for (const candidate of zhCandidates.slice(0, 2)) {
      const readme = await fetchReadmeAtPath(fullName, candidate);
      if (!readme || !readme.content_md) continue;
      readmes.push({
        readme_path: readme.readme_path,
        language_code: "zh",
        is_translated: false,
        source_url: readme.source_url,
        content_md: readme.content_md,
      });
      seenPaths.add(readme.readme_path.toLowerCase());
    }
  }

  return readmes;
}

async function fetchReadmeQueue(mode, limit) {
  const base = deriveReadmeQueueUrl();
  if (!base) return [];
  const url = new URL(base);
  url.searchParams.set("mode", mode);
  url.searchParams.set("limit", String(limit));
  try {
    const res = await fetchWithTimeout(url.toString(), {
      headers: {
        "Accept": "application/json",
        "Authorization": `Bearer ${env.ingestToken}`,
        "User-Agent": "GIR-Discover",
      },
    }, 30000);
    if (!res.ok) {
      console.warn(`Readme queue ${mode} ${res.status}: ${await res.text()}`);
      return [];
    }
    const data = await res.json();
    return Array.isArray(data.projects) ? data.projects : [];
  } catch (error) {
    console.warn(`Readme queue ${mode} error: ${error.message}`);
    return [];
  }
}

async function postReadmes(items, extraFlags = {}) {
  if (!items.length) return;
  const url = deriveReceiveReadmesUrl();
  if (!url) {
    console.warn("No receive_readmes URL derived; skipping readme post");
    return;
  }
  const payload = Object.assign({ projects: items }, extraFlags || {});
  const form = new URLSearchParams();
  form.set("payload_b64", Buffer.from(JSON.stringify(payload), "utf8").toString("base64"));
  const res = await fetchWithTimeout(url, {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
      "Authorization": `Bearer ${env.ingestToken}`,
    },
    body: form.toString(),
  }, 60000);
  const text = await res.text();
  if (!res.ok) {
    throw new Error(`Receive readmes ${res.status}: ${text}`);
  }
  console.log(`Readme ingest: ${text}`);
}

async function runReadmeFetchPass() {
  const pageSize = readmeFetchBudget > 0 ? readmeFetchBudget : (readmeFetchLimit || 20);
  if (pageSize <= 0) return;
  const maxSeconds = Math.max(60, Number(process.env.README_FETCH_MAX_SECONDS || "480"));
  const startedAt = Date.now();
  let totalProcessed = 0;
  let consecutiveFailures = 0;

  while (true) {
    const elapsed = (Date.now() - startedAt) / 1000;
    if (elapsed >= maxSeconds) {
      console.log(`Readme fetch: hit time budget ${maxSeconds}s, total_processed=${totalProcessed}`);
      break;
    }

    const queue = await fetchReadmeQueue("fetch", pageSize);
    if (!queue.length) {
      console.log(`Readme fetch: queue drained, total_processed=${totalProcessed}`);
      break;
    }

    console.log(`Readme fetch: batch of ${queue.length} (elapsed=${Math.round(elapsed)}s, total_processed=${totalProcessed})`);
    const batch = [];
    for (const project of queue) {
      if ((Date.now() - startedAt) / 1000 >= maxSeconds) break;
      try {
        const readmes = await collectProjectReadmes(project.full_name);
        batch.push({ full_name: project.full_name, readmes });
        if (requestDelayMs > 0) await sleep(requestDelayMs);
      } catch (error) {
        console.warn(`Readme fetch failed ${project.full_name}: ${error.message}`);
      }
    }

    if (!batch.length) {
      consecutiveFailures++;
      if (consecutiveFailures >= 3) {
        console.warn("Readme fetch: 3 empty batches in a row, bailing out");
        break;
      }
      continue;
    }
    consecutiveFailures = 0;

    try {
      await postReadmes(batch);
      totalProcessed += batch.length;
    } catch (error) {
      console.warn(`Readme post failed: ${error.message}`);
      break;
    }
  }
}

async function collectChineseReadmeOnly(fullName) {
  // Probe the repo root for a Chinese README variant only; do not re-fetch the default README.
  const results = [];
  const contents = await fetchRepoContentsRoot(fullName);
  if (!Array.isArray(contents)) return results;
  const candidates = [];
  for (const entry of contents) {
    if (!entry || entry.type !== "file") continue;
    const name = String(entry.name || "");
    if (pickZhReadmeCandidate(name)) candidates.push(name);
  }
  for (const candidate of candidates.slice(0, 2)) {
    const readme = await fetchReadmeAtPath(fullName, candidate);
    if (!readme || !readme.content_md) continue;
    results.push({
      readme_path: readme.readme_path,
      language_code: "zh",
      is_translated: false,
      source_url: readme.source_url,
      content_md: readme.content_md,
    });
  }
  return results;
}

async function runReadmeRecheckZhPass() {
  const pageSize = readmeFetchBudget > 0 ? readmeFetchBudget : (readmeFetchLimit || 20);
  if (pageSize <= 0) return;
  const maxSeconds = Math.max(60, Number(process.env.README_RECHECK_MAX_SECONDS || "240"));
  const startedAt = Date.now();
  let totalProcessed = 0;

  while (true) {
    const elapsed = (Date.now() - startedAt) / 1000;
    if (elapsed >= maxSeconds) {
      console.log(`Readme recheck_zh: hit time budget ${maxSeconds}s, total_processed=${totalProcessed}`);
      break;
    }
    const queue = await fetchReadmeQueue("recheck_zh", pageSize);
    if (!queue.length) {
      console.log(`Readme recheck_zh: queue drained, total_processed=${totalProcessed}`);
      break;
    }
    console.log(`Readme recheck_zh: batch of ${queue.length} (elapsed=${Math.round(elapsed)}s, total_processed=${totalProcessed})`);
    const batch = [];
    for (const project of queue) {
      if ((Date.now() - startedAt) / 1000 >= maxSeconds) break;
      try {
        const zh = await collectChineseReadmeOnly(project.full_name);
        batch.push({ full_name: project.full_name, readmes: zh });
        if (requestDelayMs > 0) await sleep(requestDelayMs);
      } catch (error) {
        console.warn(`Readme recheck_zh failed ${project.full_name}: ${error.message}`);
        // Still mark the project as checked even on error so the queue advances.
        batch.push({ full_name: project.full_name, readmes: [] });
      }
    }
    if (!batch.length) break;
    try {
      await postReadmes(batch, { mark_zh_checked: true });
      totalProcessed += batch.length;
    } catch (error) {
      console.warn(`Readme recheck_zh post failed: ${error.message}`);
      break;
    }
  }
}

function md5Hex(input) {
  return createHash("md5").update(String(input || ""), "utf8").digest("hex");
}

async function processSingleRefresh(project, config, options = {}) {
  const fullName = project.full_name;
  const repo = await repoDetails(fullName);
  if (!repo) {
    return { ok: false, error: "repo_unavailable" };
  }
  repo.source_platform = "backfill";
  repo.source_tag = "all_projects";
  repo.source_rank = 0;
  repo.source_score = scoreRepo(repo);

  const readmes = await collectProjectReadmes(fullName);
  let readmeMd5 = null;
  let englishReadmeMd5 = null;
  if (readmes.length) {
    const enReadme = readmes.find((r) => r.language_code === "en" && !r.is_translated);
    const zhReadme = readmes.find((r) => !r.is_translated && String(r.language_code || "").startsWith("zh"));
    if (zhReadme) readmeMd5 = md5Hex(zhReadme.content_md);
    if (enReadme) englishReadmeMd5 = md5Hex(enReadme.content_md);
    if (!readmeMd5 && englishReadmeMd5) readmeMd5 = englishReadmeMd5;
    try {
      await postReadmes([{ full_name: fullName, readmes }]);
    } catch (err) {
      console.warn(`Refresh: readme post failed ${fullName}: ${err.message}`);
    }
  }

  const history = await fetchProjectHistory(fullName);
  const enReadme2 = readmes.find((r) => r.language_code === "en" && !r.is_translated);
  const zhReadme2 = readmes.find((r) => !r.is_translated && String(r.language_code || "").startsWith("zh"));
  const sourceForAnalysis = zhReadme2 ? zhReadme2.content_md : (enReadme2 ? enReadme2.content_md : "");
  const analysis = await analyze(repo, sourceForAnalysis, history, config, {
    refresh_reasons: project.reasons || [],
    previous_report_id: project.previous_report_id || null,
    previous_stars: history?.[0] ? null : null, // analyze itself reads from history; passing reasons is enough
  });
  const payload = projectPayload(repo, analysis, {
    readme_md5: readmeMd5,
    reasons: project.reasons || [],
  });
  await ingest([payload], 1, { mark_refreshed: true });
  return { ok: true };
}

async function runRefreshPass(mode, config) {
  const concurrency = Math.max(1, Math.min(200, Number(process.env.REFRESH_CONCURRENCY || "10")));
  const pageSize = Math.max(concurrency, Math.min(200, Number(process.env.REFRESH_DUE_PAGE_SIZE || String(concurrency * 2))));
  const budgetEnv = mode === "refresh_all" ? "REFRESH_ALL_MAX_SECONDS" : "REFRESH_DUE_MAX_SECONDS";
  const defaultBudget = mode === "refresh_all" ? 18000 : 1200;
  const maxSeconds = Math.max(60, Number(process.env[budgetEnv] || String(defaultBudget)));
  const scanLimit = Math.max(50, Math.min(500, Number(process.env.REFRESH_SCAN_LIMIT || "200")));
  const startedAt = Date.now();
  let totalProcessed = 0;
  let totalSkipped = 0;
  let cursor = 0;
  console.log(`Refresh ${mode}: starting (concurrency=${concurrency}, page=${pageSize}, scan=${scanLimit}, budget=${maxSeconds}s)`);

  while (true) {
    const elapsed = (Date.now() - startedAt) / 1000;
    if (elapsed >= maxSeconds) {
      console.log(`Refresh ${mode}: hit time budget ${maxSeconds}s, total_processed=${totalProcessed}, skipped=${totalSkipped}`);
      break;
    }

    const url = new URL(deriveReadmeQueueUrl());
    url.searchParams.set("mode", mode);
    url.searchParams.set("limit", String(pageSize));
    url.searchParams.set("cursor", String(cursor));
    if (mode === "refresh_due") {
      url.searchParams.set("scan", String(scanLimit));
    }
    let queueData;
    try {
      let lastError = null;
      for (let attempt = 0; attempt < 3; attempt++) {
        try {
          const res = await fetchWithTimeout(url.toString(), {
            headers: {
              "Accept": "application/json",
              "Authorization": `Bearer ${env.ingestToken}`,
              "User-Agent": "GIR-Discover",
            },
          }, 30000);
          if (!res.ok) {
            lastError = new Error(`Refresh ${mode} queue ${res.status}: ${await res.text()}`);
            if (attempt < 2) { await sleep(3000); continue; }
          } else {
            queueData = await res.json();
            lastError = null;
            break;
          }
        } catch (err) {
          lastError = err;
          if (attempt < 2) { await sleep(3000); continue; }
        }
      }
      if (lastError) {
        console.warn(`Refresh ${mode} queue error after retries: ${lastError.message}`);
        break;
      }
    } catch (error) {
      console.warn(`Refresh ${mode} queue fatal: ${error.message}`);
      break;
    }

    const projects = Array.isArray(queueData.projects) ? queueData.projects : [];
    const scanned = Number(queueData.scanned || projects.length);
    const skipped = Number(queueData.skipped || 0);
    const nextCursor = queueData.next_cursor;
    totalSkipped += skipped;

    if (!projects.length) {
      if (nextCursor === null || nextCursor === undefined) {
        console.log(`Refresh ${mode}: scan exhausted, total_processed=${totalProcessed}, skipped=${totalSkipped}`);
        break;
      }
      // No projects matched in this scan window; advance cursor and continue.
      cursor = Number(nextCursor);
      continue;
    }

    console.log(`Refresh ${mode}: batch of ${projects.length} (cursor=${cursor}, scanned=${scanned}, batch_skipped=${skipped}, elapsed=${Math.round(elapsed)}s, total_processed=${totalProcessed})`);

    // Worker-pool style concurrent processing.
    const queueIter = projects[Symbol.iterator]();
    const workers = [];
    for (let i = 0; i < Math.min(concurrency, projects.length); i++) {
      workers.push((async () => {
        while (true) {
          if ((Date.now() - startedAt) / 1000 >= maxSeconds) return;
          const next = queueIter.next();
          if (next.done) return;
          const project = next.value;
          try {
            const result = await processSingleRefresh(project, config);
            if (result && result.ok) {
              totalProcessed++;
            } else {
              console.warn(`Refresh ${mode} failed ${project.full_name}: ${(result && result.error) || "unknown"}`);
            }
          } catch (error) {
            console.warn(`Refresh ${mode} crashed ${project.full_name}: ${error.message}`);
          }
        }
      })());
    }
    await Promise.all(workers);

    if (nextCursor === null || nextCursor === undefined) {
      console.log(`Refresh ${mode}: scan exhausted after batch, total_processed=${totalProcessed}, skipped=${totalSkipped}`);
      break;
    }
    cursor = Number(nextCursor);
  }
}

async function runRefreshDuePass(config) {
  return runRefreshPass("refresh_due", config);
}

async function runRefreshAllPass(config) {
  return runRefreshPass("refresh_all", config);
}

async function analyze(repo, readme, history = [], config = defaultConfig, extras = {}) {
  const res = await fetchWithTimeout(`${env.deepseekBaseUrl.replace(/\/$/, "")}/v1/chat/completions`, {
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
        { role: "system", content: systemPrompt(config) },
        { role: "user", content: userPrompt(repo, readme, history, config, extras) },
      ],
    }),
  }, 90000);
  if (!res.ok) {
    throw new Error(`DeepSeek ${res.status}: ${await res.text()}`);
  }
  const data = await res.json();
  const content = data?.choices?.[0]?.message?.content || "{}";
  return JSON.parse(content);
}

async function ingestBatch(projects, extraPayload = {}) {
  const payload = Object.assign({
    run_type: runType,
    period_type: periodType,
    report_date: today,
    projects,
  }, extraPayload || {});
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

async function ingest(projects, batchSize = ingestBatchSize, extraPayload = {}) {
  for (let index = 0; index < projects.length; index += batchSize) {
    await ingestBatch(projects.slice(index, index + batchSize), extraPayload);
    if (ingestDelayMs > 0 && index + batchSize < projects.length) {
      await sleep(ingestDelayMs);
    }
  }
}

function projectPayload(repo, analysis, extras = {}) {
  const enriched = typeof analysis === "object" && analysis !== null
    ? {
        ...analysis,
        repo_snapshot: {
          stars: Number(repo.stargazers_count || 0),
          forks: Number(repo.forks_count || 0),
          pushed_at: repo.pushed_at || null,
          readme_md5: extras.readme_md5 || null,
        },
        refresh_reasons: Array.isArray(extras.reasons) ? extras.reasons : undefined,
      }
    : analysis;
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
    analysis: enriched,
  };
}

async function main() {
  const config = applyDynamicSearchConfig(applySeedConfig(applyRuntimeOverrides(await loadDiscoverConfig())));
  // Override DeepSeek credentials from DB config if provided (takes priority over GitHub Secrets).
  if (config.deepseek_api_key) env.deepseekKey = config.deepseek_api_key;
  if (config.deepseek_base_url) env.deepseekBaseUrl = config.deepseek_base_url;
  if (config.deepseek_model) env.deepseekModel = config.deepseek_model;
  // Override concurrency from DB config.
  if (config.analyze_concurrency) {
    process.env.ANALYZE_CONCURRENCY = String(config.analyze_concurrency);
    process.env.REFRESH_CONCURRENCY = String(config.analyze_concurrency);
  }
  console.log(`Discover config: mode=${backfillMode ? (backlogPendingOnly ? "backlog" : "backfill") : (seedMode ? "seed" : "normal")}, max=${config.max_projects}, per_page=${config.per_page}, platforms=${config.platforms.join(",")}, topics=${config.topics.join(",")}, extra_queries=${config.extra_queries.length}, report_date=${today}, deepseek_base=${env.deepseekBaseUrl}, concurrency=${process.env.ANALYZE_CONCURRENCY || "10"}`);
  if (runType === "refresh_all") {
    console.log("RUN_TYPE=refresh_all: re-analyzing every project once with the new repo_snapshot schema");
    await runRefreshAllPass(config);
    return;
  }
  if (backfillMode) {
    if (backlogPendingOnly && config.backlog_enabled === false) {
      console.log("Backlog auto-run disabled by config; skipping");
      return;
    }
    if (config.readme_fetch_enabled !== false) {
      readmeFetchBudget = Math.max(0, Math.min(readmeFetchLimit, Number(config.readme_per_run ?? readmeFetchLimit)));
      await runReadmeFetchPass();
      await runReadmeRecheckZhPass();
    }
    await runBackfill(config);
    await runRefreshDuePass(config);
    return;
  }
  if (periodType === "daily" && config.daily_enabled === false) {
    console.log("Daily discover disabled by config; skipping");
    return;
  }
  if (periodType === "weekly" && config.weekly_enabled === false) {
    console.log("Weekly discover disabled by config; skipping");
    return;
  }
  const buckets = await sourceBuckets(config);
  const rankingCandidates = rankingCandidatesFromBuckets(buckets);
  if (rankingCandidates.length) {
    console.log(`Ingesting raw ranking candidates: ${rankingCandidates.length}`);
    await ingest(rankingCandidates.map((repo) => projectPayload(repo, { raw_rank_only: true })), rawIngestBatchSize);
  }

  const repos = config.analyze_all ? rankingCandidates : selectProjectsFromBuckets(buckets, config);
  const concurrency = Math.max(1, Math.min(200, Number(process.env.ANALYZE_CONCURRENCY || "10")));
  let analyzedCount = 0;
  const analyzedBatch = [];
  const flushLock = { running: false };
  const flushIfFull = async () => {
    if (flushLock.running) return;
    if (analyzedBatch.length < analysisIngestBatchSize) return;
    flushLock.running = true;
    try {
      const slice = analyzedBatch.splice(0, analysisIngestBatchSize);
      await ingest(slice, analysisIngestBatchSize);
    } finally {
      flushLock.running = false;
    }
  };

  const repoIter = repos[Symbol.iterator]();
  const workers = [];
  for (let i = 0; i < Math.min(concurrency, repos.length); i++) {
    workers.push((async () => {
      while (true) {
        const next = repoIter.next();
        if (next.done) return;
        const repo = next.value;
        console.log(`Analyzing ${repo.full_name}`);
        try {
          const history = await fetchProjectHistory(repo.full_name);
          const readme = await getReadme(repo);
          const analysis = await analyze(repo, readme, history, config);
          analyzedBatch.push(projectPayload(repo, analysis));
          analyzedCount++;
          await flushIfFull();
        } catch (error) {
          console.error(`Failed ${repo.full_name}: ${error.message}`);
        }
      }
    })());
  }
  await Promise.all(workers);
  if (analyzedBatch.length) {
    await ingest(analyzedBatch, analysisIngestBatchSize);
  }
  if (!analyzedCount) {
    throw new Error("No projects analyzed");
  }
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
