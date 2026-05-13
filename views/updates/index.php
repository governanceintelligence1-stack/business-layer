<?php $pageTitle = 'Updates'; ?>

<style>
  .updates-shell {
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: minmax(0, 1fr) 320px;
    gap: 1.25rem;
  }
  .updates-main { display: grid; gap: 1rem; }
  .updates-side {
    position: sticky;
    top: 50%;
    transform: translateY(-35%);
    align-self: start;
    height: fit-content;
  }
  .updates-ref-list { display: grid; gap: .45rem; }
  .updates-ref-link {
    display: block;
    padding: .55rem .7rem;
    border: 1px solid var(--border);
    border-radius: 10px;
    text-decoration: none;
    font-size: .86rem;
    color: var(--foreground);
    background: var(--card);
  }
  .updates-ref-link:hover { background: var(--muted); }
  .updates-article { scroll-margin-top: 6rem; }
  .updates-article h3 { margin: 0 0 .35rem; font-size: 1.05rem; }
  .updates-meta { color: var(--muted-foreground); font-size: .78rem; margin-bottom: .6rem; }
  .updates-search {
    width: 100%;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: .6rem .7rem;
    font-size: .86rem;
    font-family: inherit;
    margin-bottom: .75rem;
    background: var(--card);
    color: var(--foreground);
  }
  .updates-side-list { list-style: none; display: grid; gap: .45rem; padding: 0; margin: 0; }
  .updates-side-list a {
    text-decoration: none;
    color: var(--foreground);
    font-size: .82rem;
    display: block;
    padding: .45rem .55rem;
    border-radius: 8px;
  }
  .updates-side-list a:hover { background: var(--muted); }
  @media (max-width: 980px) {
    .updates-shell { grid-template-columns: 1fr; }
    .updates-side { position: static; transform: none; }
  }
</style>

<?php $articles = is_array($articles ?? null) ? $articles : []; ?>

<div class="updates-shell">
  <section class="updates-main">
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Article References</h2>
      </div>
      <div class="updates-ref-list" id="updatesRefList">
        <?php foreach ($articles as $article): ?>
          <a class="updates-ref-link" data-article-link href="#<?= htmlspecialchars($article['id']) ?>" data-title="<?= htmlspecialchars($article['title']) ?>">
            <?= htmlspecialchars($article['title']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if (empty($articles)): ?>
      <article class="card updates-article">
        <h3>No updates published yet</h3>
        <div class="updates-meta">Please check back later.</div>
      </article>
    <?php endif; ?>

    <?php foreach ($articles as $article): ?>
      <article class="card updates-article" id="<?= htmlspecialchars($article['id']) ?>" data-article-card data-title="<?= htmlspecialchars($article['title']) ?>">
        <h3><?= htmlspecialchars($article['title']) ?></h3>
        <div class="updates-meta"><?= htmlspecialchars((string)($article['article_date'] ?? '')) ?></div>
        <p><strong>Title:</strong> <?= htmlspecialchars($article['title']) ?></p>
        <p><?= htmlspecialchars((string)($article['body'] ?? $article['summary'] ?? '')) ?></p>
      </article>
    <?php endforeach; ?>
  </section>

  <aside class="updates-side">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title" style="font-size:.95rem;">Navigation</h3>
      </div>
      <input id="updatesSearchInput" class="updates-search" type="search" placeholder="Search articles...">
      <div style="font-size:.78rem; font-weight:600; margin-bottom:.45rem; color:var(--muted-foreground);">Recently Searched</div>
      <ul id="recentSearchesList" class="updates-side-list"></ul>
    </div>
  </aside>
</div>

<script>
  (function () {
    var searchInput = document.getElementById('updatesSearchInput');
    var articleCards = Array.prototype.slice.call(document.querySelectorAll('[data-article-card]'));
    var articleLinks = Array.prototype.slice.call(document.querySelectorAll('[data-article-link]'));
    var recentList = document.getElementById('recentSearchesList');
    var key = 'updates_recent_searches';

    function getRecent() {
      try { return JSON.parse(localStorage.getItem(key) || '[]'); } catch (e) { return []; }
    }
    function saveRecent(items) {
      localStorage.setItem(key, JSON.stringify(items.slice(0, 6)));
    }
    function addRecent(term) {
      var value = (term || '').trim();
      if (!value) return;
      var curr = getRecent().filter(function (x) { return x.toLowerCase() !== value.toLowerCase(); });
      curr.unshift(value);
      saveRecent(curr);
      renderRecent();
    }
    function renderRecent() {
      if (!recentList) return;
      var items = getRecent();
      recentList.innerHTML = '';
      if (!items.length) {
        var li = document.createElement('li');
        li.textContent = 'No recent searches';
        li.style.color = '#94a3b8';
        li.style.fontSize = '.8rem';
        recentList.appendChild(li);
        return;
      }
      items.forEach(function (item) {
        var li = document.createElement('li');
        var a = document.createElement('a');
        a.href = '#';
        a.textContent = item;
        a.addEventListener('click', function (e) {
          e.preventDefault();
          searchInput.value = item;
          applyFilter(item);
        });
        li.appendChild(a);
        recentList.appendChild(li);
      });
    }
    function applyFilter(term) {
      var q = (term || '').toLowerCase().trim();
      articleCards.forEach(function (card) {
        var t = (card.getAttribute('data-title') || '').toLowerCase();
        card.style.display = (!q || t.indexOf(q) >= 0) ? '' : 'none';
      });
      articleLinks.forEach(function (link) {
        var t = (link.getAttribute('data-title') || '').toLowerCase();
        link.style.display = (!q || t.indexOf(q) >= 0) ? '' : 'none';
      });
    }

    searchInput.addEventListener('input', function () { applyFilter(searchInput.value); });
    searchInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') addRecent(searchInput.value);
    });
    articleLinks.forEach(function (link) {
      link.addEventListener('click', function () {
        addRecent(link.getAttribute('data-title') || '');
      });
    });
    renderRecent();
  })();
</script>
