<?php global $current_blog ?>
<style>
.gss-results-list {
  display: grid;
  gap: 16px;
}

.gss-result-card {
  background: #fff;
  border: 1px solid rgba(17, 24, 39, 0.08);
  border-radius: 14px;
  padding: 18px 20px;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
}

.gss-result-topline {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 14px;
  align-items: center;
  margin-bottom: 8px;
  font-size: 13px;
}

.gss-result-site {
  color: #0f172a;
  font-weight: 700;
  text-decoration: none;
}

.gss-result-url {
  color: #2f6f3e;
  text-decoration: none;
  word-break: break-all;
}

.gss-result-title {
  margin: 0 0 8px;
  font-size: 24px;
  line-height: 1.25;
}

.gss-result-title a {
  color: #1d4f91;
  text-decoration: none;
}

.gss-result-title a:hover,
.gss-result-title a:focus,
.gss-result-site:hover,
.gss-result-site:focus,
.gss-result-url:hover,
.gss-result-url:focus {
  text-decoration: underline;
}

.gss-result-excerpt {
  margin: 0;
  color: #334155;
  line-height: 1.65;
}

.gss-hit {
  background: #fff2a8;
  color: inherit;
  padding: 0 2px;
  border-radius: 3px;
}

.gss-results-more {
  display: flex;
  justify-content: center;
  margin-top: 18px;
}

.gss-load-more {
  min-width: 220px;
  min-height: 46px;
  padding: 12px 18px;
  border: 0;
  border-radius: 999px;
  background: #0f4c81;
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}

.gss-load-more[disabled] {
  opacity: 0.7;
  cursor: wait;
}

.gss-no-results,
.gss-search-loading {
  padding: 24px 18px;
  border-radius: 14px;
  background: #f8fafc;
  color: #64748b;
  text-align: center;
}

@media (max-width: 640px) {
  .gss-result-card {
    padding: 16px;
  }

  .gss-result-title {
    font-size: 20px;
  }
}
</style>
<form id="gss-ajax-search-form" action="#" method="get">
  <div class="gss-ajax-controls" style="display:flex;align-items:stretch;gap:12px;width:100%;flex-wrap:wrap;">
    <div class="gss-ajax-field" style="flex:1 1 320px;min-width:0;">
      <input type="text" name="phrase" id="gss-ajax-phrase" style="width:100%;box-sizing:border-box;min-height:48px;" value="<?php echo esc_attr( stripslashes( global_site_search_get_phrase() ) ) ?>">
    </div>
    <div class="gss-ajax-action" style="flex:0 0 160px;min-width:160px;">
	      <input type="submit" value="<?php echo esc_attr__( 'Suchen', 'postindexer' ); ?>" style="display:block;width:100%;min-width:160px;min-height:48px;box-sizing:border-box;cursor:pointer;white-space:nowrap;">
    </div>
  </div>
</form>
<div id="gss-ajax-results"></div>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var form = document.getElementById('gss-ajax-search-form');
  var input = document.getElementById('gss-ajax-phrase');
  var results = document.getElementById('gss-ajax-results');
  var observer = null;

  if (!form || !input || !results) {
    return;
  }

  function setLoading(append) {
    if (!append) {
      results.innerHTML = '<div class="gss-search-loading">Suche läuft...</div>';
      return;
    }

    var loadMoreButton = results.querySelector('.gss-load-more');
    if (loadMoreButton) {
      loadMoreButton.disabled = true;
      loadMoreButton.textContent = 'Lade mehr...';
    }
  }

  function connectObserver() {
    if (observer) {
      observer.disconnect();
    }

    var loadMoreButton = results.querySelector('.gss-load-more');
    if (!loadMoreButton || !('IntersectionObserver' in window)) {
      return;
    }

    observer = new IntersectionObserver(function(entries) {
      entries.forEach(function(entry) {
        if (entry.isIntersecting) {
          observer.disconnect();
          loadMoreButton.click();
        }
      });
    }, {
      rootMargin: '180px 0px'
    });

    observer.observe(loadMoreButton);
  }

  function mergeResults(html) {
    var temp = document.createElement('div');
    temp.innerHTML = html;

    var incomingList = temp.querySelector('.gss-results-list');
    var currentList = results.querySelector('.gss-results-list');
    var currentMore = results.querySelector('.gss-results-more');
    var incomingMore = temp.querySelector('.gss-results-more');

    if (!currentList || !incomingList) {
      results.innerHTML = html;
      connectObserver();
      return;
    }

    while (incomingList.firstChild) {
      currentList.appendChild(incomingList.firstChild);
    }

    if (currentMore) {
      currentMore.remove();
    }

    if (incomingMore) {
      results.appendChild(incomingMore);
    }

    connectObserver();
  }

  function fetchResults(page, append) {
    var phrase = input.value.trim();
    if (!phrase) {
      results.innerHTML = '';
      return;
    }

    setLoading(append);

    var params = new URLSearchParams();
    params.set('gss_ajax', '1');
    params.set('phrase', phrase);
    params.set('page', String(page));

    fetch(window.location.pathname + '?' + params.toString())
      .then(function(response) { return response.text(); })
      .then(function(html) {
        if (append) {
          mergeResults(html);
        } else {
          results.innerHTML = html;
          connectObserver();
        }
      })
      .catch(function() {
        results.innerHTML = '<div class="gss-no-results">Fehler bei der Suche.</div>';
      });
  }

  form.addEventListener('submit', function(e) {
    e.preventDefault();
    fetchResults(1, false);
  });

  results.addEventListener('click', function(e) {
    var button = e.target.closest('.gss-load-more');
    if (!button) {
      return;
    }

    e.preventDefault();
    if (button.disabled) {
      return;
    }

    fetchResults(parseInt(button.getAttribute('data-next-page') || '2', 10), true);
  });
});
</script>