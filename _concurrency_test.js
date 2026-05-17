// _concurrency_test.js — 并发阶梯测试 (claude-sonnet-4-6)

const BASE_URL = "https://cnl.jians.org";
const API_KEY = "sk-ECyx38PypD1RV8P0U8lSm0Aa3q7fNqftJx0JjqYCfbSsZf3T";
const MODEL = "claude-opus-4-5-20251101";

async function probe(id) {
  const start = Date.now();
  try {
    const res = await fetch(`${BASE_URL}/v1/chat/completions`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Authorization": `Bearer ${API_KEY}`,
      },
      body: JSON.stringify({
        model: MODEL,
        max_tokens: 1,
        messages: [
          { role: "user", content: "hi" },
        ],
      }),
    });
    const elapsed = Date.now() - start;
    if (!res.ok) {
      const text = await res.text();
      return { id, ok: false, status: res.status, elapsed, error: text.slice(0, 200) };
    }
    const data = await res.json();
    const content = data?.choices?.[0]?.message?.content || "";
    return { id, ok: true, status: res.status, elapsed, content: content.slice(0, 50) };
  } catch (err) {
    return { id, ok: false, status: 0, elapsed: Date.now() - start, error: err.message };
  }
}

async function testConcurrency(n) {
  console.log(`\n=== Testing ${n} concurrent requests ===`);
  const start = Date.now();
  const results = await Promise.all(Array.from({ length: n }, (_, i) => probe(i)));
  const totalElapsed = Date.now() - start;

  const succeeded = results.filter(r => r.ok).length;
  const failed = results.filter(r => !r.ok);
  const avgElapsed = Math.round(results.filter(r => r.ok).reduce((s, r) => s + r.elapsed, 0) / (succeeded || 1));

  console.log(`  ✓ ${succeeded}/${n} succeeded | Total: ${totalElapsed}ms | Avg latency: ${avgElapsed}ms`);
  if (failed.length) {
    // 按 status 分组统计
    const byStatus = {};
    failed.forEach(f => {
      byStatus[f.status] = (byStatus[f.status] || 0) + 1;
    });
    console.log(`  ✗ ${failed.length} failed:`, Object.entries(byStatus).map(([s, c]) => `${s}×${c}`).join(', '));
    // 打印第一个错误详情
    console.log(`  Sample error: ${failed[0].error?.slice(0, 150)}`);
  }

  return { n, succeeded, failed: failed.length, totalElapsed };
}

async function main() {
  console.log(`Target: ${BASE_URL} | Model: ${MODEL}`);
  console.log(`Request: max_tokens=1, message="hi" (minimal token usage)\n`);

  const steps = [1, 2, 5, 10, 20, 30, 50, 75, 100];
  const results = [];

  for (const n of steps) {
    const result = await testConcurrency(n);
    results.push(result);

    // 如果失败率超过 50%，停止测试
    if (result.failed > result.succeeded) {
      console.log(`\n>>> Stopping: failure rate > 50% at ${n} concurrent`);
      break;
    }

    // 间隔 3 秒避免触发速率限制
    await new Promise(r => setTimeout(r, 3000));
  }

  console.log(`\n========== SUMMARY ==========`);
  console.log(`Concurrency | Success | Failed | Total Time`);
  console.log(`------------|---------|--------|----------`);
  results.forEach(r => {
    console.log(`${String(r.n).padStart(11)} | ${String(r.succeeded).padStart(7)} | ${String(r.failed).padStart(6)} | ${r.totalElapsed}ms`);
  });

  const lastGood = results.filter(r => r.failed === 0).pop();
  const firstBad = results.find(r => r.failed > 0);
  if (lastGood && firstBad) {
    console.log(`\n>>> Estimated concurrency limit: between ${lastGood.n} and ${firstBad.n}`);
  } else if (!firstBad) {
    console.log(`\n>>> All steps passed! Limit is >= ${results[results.length - 1].n}`);
  } else {
    console.log(`\n>>> Even ${results[0].n} concurrent failed.`);
  }
}

main();
