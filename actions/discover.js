const runType = process.env.RUN_TYPE || process.argv[2] || "daily";
const periodType = runType === "weekly" ? "weekly" : runType === "manual" ? "manual" : "daily";
const ingestBatchSize = Math.max(1, Number(process.env.INGEST_BATCH_SIZE || "1"));
const rawIngestBatchSize = Math.max(1, Number(process.env.RAW_INGEST_BATCH_SIZE || "50"));
const analysisIngestBatchSize = Math.max(1, Number(process.env.ANALYSIS_INGEST_BATCH_SIZE || "20"));
const requestDelayMs = Math.max(0, Number(process.env.REQUEST_DELAY_MS || "0"));
const ingestDelayMs = Math.max(0, Number(process.env.INGEST_DELAY_MS || "0"));
const githubSearchDelayMs = Math.max(0, Number(process.env.GITHUB_SEARCH_DELAY_MS || "3500"));
const githubRateLimitRetries = Math.max(0, Number(process.env.GITHUB_RATE_LIMIT_RETRIES || "2"));
const githubMaxRateLimitWaitMs = Math.max(0, Number(process.env.GITHUB_MAX_RATE_LIMIT_WAIT_MS || "60000"));
const githubSearchSpecOffset = Math.max(0, Number(process.env.GITHUB_SEARCH_SPEC_OFFSET || "0"));
const githubSearchSpecLimit = Math.max(0, Number(process.env.GITHUB_SEARCH_SPEC_LIMIT || "0"));
const seedMode = ["1", "true", "yes", "on"].includes(String(process.env.SEED_MODE || "").toLowerCase());

const env = {
  githubToken: process.env.GIR_GITHUB_SEARCH_TOKEN || process.env.GH_PAT || process.env.GITHUB_TOKEN || "",
  deepseekKey: process.env.DEEPSEEK_API_KEY || "",
  deepseekBaseUrl: process.env.DEEPSEEK_BASE_URL || "https://api.deepseek.com",
  deepseekModel: process.env.DEEPSEEK_MODEL || "deepseek-chat",
  ingestUrl: process.env.APP_INGEST_URL || "",
  ingestToken: process.env.APP_INGEST_TOKEN || "",
  configUrl: process.env.APP_CONFIG_URL || "",
  platformOverride: process.env.DISCOVER_PLATFORMS || "",
};

for (const [key, value] of Object.entries(env)) {
  if (!value && ["deepseekKey", "ingestUrl", "ingestToken"].includes(key)) {
    throw new Error(`Missing required environment: ${key}`);
  }
}

const defaultConfig = {
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
  platforms: ["github_trending", "github_search", "ossinsight", "trendshift", "reporank", "gitrepotrend"],
  deepseek_system_prompt: defaultSystemPrompt(),
  deepseek_task_prompt: defaultTaskPrompt(),
};

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

const today = new Date().toISOString().slice(0, 10);

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
      platforms,
      deepseek_system_prompt: String(config.deepseek_system_prompt || defaultConfig.deepseek_system_prompt).trim() || defaultConfig.deepseek_system_prompt,
      deepseek_task_prompt: String(config.deepseek_task_prompt || defaultConfig.deepseek_task_prompt).trim() || defaultConfig.deepseek_task_prompt,
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
    "你的目标是把项目讲成人能快速理解的中文：它做什么、为什么值得关注、适合谁、怎么用、有什么可借鉴点。",
    "不要把本站部署环境当作通用评价标准；除非项目本身就是 PHP、建站、部署、虚拟主机或运维工具，否则不要讨论“是否适合传统 PHP 虚拟主机”。",
    "你必须用中文输出严格 JSON，不要 Markdown，不要解释。",
    "评分为 1 到 10 的整数。",
    "play_score 衡量项目是否有趣、是否值得点开体验、是否能带来灵感。",
    "useful_score 衡量项目是否解决真实问题、是否有明确使用价值。",
    "maturity_score 衡量项目成熟度，综合 Stars、Forks、最近更新、文档完整度和社区活跃度。",
    "difficulty 衡量理解、部署、改造或复刻成本，只能输出 低、中、高。",
  ].join("\n");
}

function defaultTaskPrompt() {
  return "为这次榜单命中生成一条新的中文解说。即使历史里已经分析过同一个项目，也不要复用旧文案；请结合最近几次解说，判断这次是否有新功能、热度变化、定位变化或值得重新关注的原因。表达要说人话，避免空泛夸奖，重点说明：项目一句话用途、解决的真实问题、为什么上榜或变热、适合谁用、上手方式或可借鉴点、主要风险。不要默认讨论是否适合传统 PHP 虚拟主机，也不要因为项目需要 Docker、Python、Node 或 GPU 就直接给出“暂不关注”；只有当项目主题与建站/部署环境直接相关时，才在风险里简短提一句环境要求。";
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

function userPrompt(repo, readme, history = [], config = defaultConfig) {
  return JSON.stringify({
    task: String(config.deepseek_task_prompt || defaultTaskPrompt()).trim() || defaultTaskPrompt(),
    required_schema: {
      one_sentence: "一句话介绍",
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
      change_note: "结合 previous_analyses 写出本次相对最近几次解说的变化观察；如果没有明显变化，也要说明没有明显变化以及本次仍值得或不值得关注的原因",
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

async function analyze(repo, readme, history = [], config = defaultConfig) {
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
        { role: "user", content: userPrompt(repo, readme, history, config) },
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

async function ingest(projects, batchSize = ingestBatchSize) {
  for (let index = 0; index < projects.length; index += batchSize) {
    await ingestBatch(projects.slice(index, index + batchSize));
    if (ingestDelayMs > 0 && index + batchSize < projects.length) {
      await sleep(ingestDelayMs);
    }
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
  const config = applySeedConfig(applyRuntimeOverrides(await loadDiscoverConfig()));
  console.log(`Discover config: mode=${seedMode ? "seed" : "normal"}, max=${config.max_projects}, per_page=${config.per_page}, platforms=${config.platforms.join(",")}, topics=${config.topics.join(",")}, extra_queries=${config.extra_queries.length}`);
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
  let analyzedCount = 0;
  let analyzedBatch = [];
  for (const repo of repos) {
    console.log(`Analyzing ${repo.full_name}`);
    try {
      const history = await fetchProjectHistory(repo.full_name);
      const readme = await getReadme(repo);
      const analysis = await analyze(repo, readme, history, config);
      analyzedBatch.push(projectPayload(repo, analysis));
      analyzedCount++;
      if (analyzedBatch.length >= analysisIngestBatchSize) {
        await ingest(analyzedBatch, analysisIngestBatchSize);
        analyzedBatch = [];
      }
      if (requestDelayMs > 0) {
        await sleep(requestDelayMs);
      }
    } catch (error) {
      console.error(`Failed ${repo.full_name}: ${error.message}`);
      if (requestDelayMs > 0) {
        await sleep(requestDelayMs);
      }
    }
  }
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
