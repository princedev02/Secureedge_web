<?php
/**
 * SecureEdge blog. Content is authored in the CRM (crm.secureedge.in) and
 * rendered here so the URLs, and their SEO value, live on this domain.
 *
 * Responses are cached to disk. If the CRM is unreachable we serve the last
 * good copy rather than an error page - a marketing site should never go down
 * because an internal system did.
 */

const CRM_API   = 'https://crm.secureedge.in/api/blog/posts';
const CACHE_DIR = __DIR__ . '/cache';
const CACHE_TTL = 300;          // seconds before we re-check the CRM
const STALE_TTL = 604800;       // a week: how long a stale copy may still be served

function cache_path(string $key): string
{
    return CACHE_DIR . '/' . sha1($key) . '.json';
}

/**
 * Fetch JSON from the CRM, with a disk cache in front and stale-on-error behind.
 */
function crm_get(string $url): ?array
{
    $file = cache_path($url);
    $now  = time();

    if (is_file($file) && ($now - filemtime($file)) < CACHE_TTL) {
        $cached = json_decode((string) file_get_contents($file), true);
        if (is_array($cached)) {
            return $cached;
        }
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $body   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status === 200 && is_string($body)) {
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            if (! is_dir(CACHE_DIR)) {
                @mkdir(CACHE_DIR, 0755, true);
            }
            @file_put_contents($file, $body);
            return $decoded;
        }
    }

    // A genuine 404 from the CRM is an answer, not a failure.
    if ($status === 404) {
        return null;
    }

    // CRM unreachable or erroring: fall back to the last good copy.
    if (is_file($file) && ($now - filemtime($file)) < STALE_TTL) {
        $cached = json_decode((string) file_get_contents($file), true);
        if (is_array($cached)) {
            return $cached;
        }
    }

    return null;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function human_date(?string $iso): string
{
    if (! $iso) {
        return '';
    }
    $ts = strtotime($iso);
    return $ts ? date('j F Y', $ts) : '';
}

$slug = isset($_GET['slug']) ? preg_replace('/[^a-z0-9\-]/', '', strtolower((string) $_GET['slug'])) : '';
$page = max(1, (int) ($_GET['page'] ?? 1));

$post  = null;
$posts = [];
$meta  = ['current_page' => 1, 'last_page' => 1, 'total' => 0];

if ($slug !== '') {
    $response = crm_get(CRM_API . '/' . rawurlencode($slug));
    if (! $response || empty($response['data'])) {
        http_response_code(404);
    } else {
        $post = $response['data'];
    }
} else {
    $response = crm_get(CRM_API . '?page=' . $page . '&per_page=9');
    $posts    = $response['data'] ?? [];
    $meta     = $response['meta'] ?? $meta;
}

$siteName = 'SecureEdge Fintech';
$baseUrl  = 'https://secureedgefintech.com';

if ($post) {
    $pageTitle = ($post['seo']['meta_title'] ?? $post['title']) . ' | ' . $siteName;
    $pageDesc  = $post['seo']['meta_description'] ?? '';
    $canonical = $post['seo']['canonical_url'] ?: $baseUrl . '/blog/' . $post['slug'];
    $ogImage   = $post['seo']['og_image'] ?? '';
    $noindex   = ! empty($post['seo']['noindex']);
} else {
    $pageTitle = 'Blog | ' . $siteName;
    $pageDesc  = 'Insights on AEPS, eKYC, digital onboarding and verification APIs for banks, NBFCs and cooperative banks in India.';
    $canonical = $baseUrl . '/blog/';
    $ogImage   = '';
    $noindex   = false;
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($pageDesc) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<?php if ($noindex): ?><meta name="robots" content="noindex,follow"><?php endif; ?>
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($pageDesc) ?>">
<meta property="og:type" content="<?= $post ? 'article' : 'website' ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<?php if ($ogImage): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif; ?>
<link rel="icon" type="image/png" href="/assets/images/se-logo-favicon.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root { --se-red:#C50000; --se-ink:#171412; --se-muted:#6E6461; --se-line:#E6E1DF; }
  body { font-family:'Outfit',system-ui,sans-serif; color:var(--se-ink); background:#fff; }
  .blog-hero { padding:72px 0 32px; border-bottom:1px solid var(--se-line); }
  .blog-hero h1 { font-weight:700; letter-spacing:-.02em; }
  .blog-hero p { color:var(--se-muted); max-width:62ch; }
  .post-card { border:1px solid var(--se-line); border-radius:12px; overflow:hidden; height:100%;
               transition:box-shadow .2s ease, transform .2s ease; background:#fff; }
  .post-card:hover { box-shadow:0 10px 30px -18px rgba(23,20,18,.45); transform:translateY(-2px); }
  .post-card img { width:100%; height:180px; object-fit:cover; display:block; }
  .post-card .body { padding:18px 20px 22px; }
  .post-card h2 { font-size:1.1rem; font-weight:600; line-height:1.35; margin:0 0 8px; }
  .post-card a { text-decoration:none; color:inherit; }
  .post-meta { font-size:.82rem; color:var(--se-muted); }
  .cat-pill { display:inline-block; font-size:.72rem; font-weight:600; letter-spacing:.06em;
              text-transform:uppercase; color:var(--se-red); background:#FBEFEC;
              padding:3px 9px; border-radius:100px; margin-bottom:10px; }
  article.post { max-width:760px; margin:0 auto; padding:48px 0 72px; }
  article.post h1 { font-weight:700; letter-spacing:-.02em; margin-bottom:12px; }
  article.post .content { font-size:1.06rem; line-height:1.75; }
  article.post .content img { max-width:100%; height:auto; border-radius:10px; margin:22px 0; }
  article.post .content h2 { font-weight:600; margin-top:34px; font-size:1.5rem; }
  article.post .content h3 { font-weight:600; margin-top:26px; font-size:1.2rem; }
  .lead-img { width:100%; border-radius:12px; margin:24px 0 8px; }
  .back-link { color:var(--se-red); text-decoration:none; font-weight:500; }
</style>
</head>
<body>

<div class="container">
<?php if ($post): ?>

  <article class="post">
    <p class="mb-3"><a class="back-link" href="/blog/">&larr; All articles</a></p>
    <?php if (! empty($post['category']['name'])): ?>
      <span class="cat-pill"><?= e($post['category']['name']) ?></span>
    <?php endif; ?>
    <h1><?= e($post['title']) ?></h1>
    <p class="post-meta">
      <?= e(human_date($post['published_at'] ?? null)) ?>
      <?php if (! empty($post['reading_minutes'])): ?>
        &middot; <?= (int) $post['reading_minutes'] ?> min read
      <?php endif; ?>
      <?php if (! empty($post['author'])): ?>
        &middot; <?= e($post['author']) ?>
      <?php endif; ?>
    </p>

    <?php if (! empty($post['featured_image'])): ?>
      <img class="lead-img" src="<?= e($post['featured_image']) ?>" alt="<?= e($post['featured_image_alt'] ?? $post['title']) ?>">
    <?php endif; ?>

    <div class="content"><?= $post['body'] ?? '' ?></div>
  </article>

<?php elseif ($slug !== ''): ?>

  <article class="post text-center">
    <h1>Article not found</h1>
    <p class="post-meta">That article may have moved or is no longer published.</p>
    <p class="mt-4"><a class="back-link" href="/blog/">Browse all articles</a></p>
  </article>

<?php else: ?>

  <div class="blog-hero">
    <h1>Insights</h1>
    <p>Practical writing on AEPS, eKYC, digital onboarding and verification APIs for banks, NBFCs and cooperative banks across India.</p>
  </div>

  <?php if (! $posts): ?>
    <p class="post-meta py-5">No articles published yet. Check back shortly.</p>
  <?php else: ?>
    <div class="row g-4 py-5">
      <?php foreach ($posts as $p): ?>
        <div class="col-md-6 col-lg-4">
          <div class="post-card">
            <a href="/blog/<?= e($p['slug']) ?>">
              <?php if (! empty($p['featured_image'])): ?>
                <img src="<?= e($p['featured_image']) ?>" alt="<?= e($p['featured_image_alt'] ?? $p['title']) ?>" loading="lazy">
              <?php endif; ?>
              <div class="body">
                <?php if (! empty($p['category']['name'])): ?>
                  <span class="cat-pill"><?= e($p['category']['name']) ?></span>
                <?php endif; ?>
                <h2><?= e($p['title']) ?></h2>
                <?php if (! empty($p['excerpt'])): ?>
                  <p class="post-meta mb-2"><?= e($p['excerpt']) ?></p>
                <?php endif; ?>
                <p class="post-meta mb-0">
                  <?= e(human_date($p['published_at'] ?? null)) ?>
                  <?php if (! empty($p['reading_minutes'])): ?>
                    &middot; <?= (int) $p['reading_minutes'] ?> min read
                  <?php endif; ?>
                </p>
              </div>
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if (($meta['last_page'] ?? 1) > 1): ?>
      <nav class="pb-5 d-flex gap-2 justify-content-center">
        <?php for ($i = 1; $i <= (int) $meta['last_page']; $i++): ?>
          <a class="btn btn-sm <?= $i === (int) $meta['current_page'] ? 'btn-dark' : 'btn-outline-secondary' ?>"
             href="/blog/?page=<?= $i ?>"><?= $i ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>

<?php endif; ?>
</div>

</body>
</html>
