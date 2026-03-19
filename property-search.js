/* property-search.js */
(function ($) {
  'use strict';

  const $form       = $('#ps-search-form');
  const $grid       = $('#ps-results');
  const $meta       = $('#ps-results-meta');
  const $pagination = $('#ps-pagination');
  const $noResults  = $('#ps-no-results');
  let currentPage   = 1;
  let isLoading     = false;

  // ── Price comma formatting ─────────────────────────────────────────────────
  $form.on('input', '#ps-min-price, #ps-max-price', function () {
    var el    = this;
    var raw   = el.value.replace(/[^0-9]/g, '');
    var caret = el.selectionStart;
    var prevCommas = (el.value.slice(0, caret).match(/,/g) || []).length;

    el.value = raw ? parseInt(raw, 10).toLocaleString('en-GB') : '';

    var newCommas = (el.value.slice(0, caret).match(/,/g) || []).length;
    el.setSelectionRange(caret + (newCommas - prevCommas), caret + (newCommas - prevCommas));
  });

  // ── Trigger search on form submit ──────────────────────────────────────────
  $form.on('submit', function (e) {
    e.preventDefault();
    currentPage = 1;
    doSearch();
  });

  // ── Live search on select change ───────────────────────────────────────────
  $form.on('change', 'select', function () {
    currentPage = 1;
    doSearch();
  });

  // ── Debounced live search on text input ────────────────────────────────────
  let debounceTimer;
  $form.on('input', '#ps-keyword, #ps-min-price, #ps-max-price', function () {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(function () {
      currentPage = 1;
      doSearch();
    }, 420);
  });

  // ── Pagination clicks ──────────────────────────────────────────────────────
  $pagination.on('click', '.ps-page-btn', function () {
    if ($(this).hasClass('is-active') || isLoading) return;
    currentPage = parseInt($(this).data('page'), 10);
    doSearch(true);
  });

  // ── Core search function ───────────────────────────────────────────────────
  function doSearch(scrollToResults) {
    if (isLoading) return;
    isLoading = true;

    const data = $form.serializeArray().reduce((obj, item) => {
      obj[item.name] = item.value;
      return obj;
    }, {});

    // Strip commas from price fields before sending
    if (data.min_price) data.min_price = data.min_price.replace(/,/g, '');
    if (data.max_price) data.max_price = data.max_price.replace(/,/g, '');

    data.action = 'property_search';
    data.nonce  = PropertySearch.nonce;
    data.paged  = currentPage;

    $noResults.prop('hidden', true);
    $grid.addClass('ps-grid--loading');

    $.ajax({
      url:      PropertySearch.ajax_url,
      type:     'POST',
      data:     data,
      success:  handleSuccess,
      error:    handleError,
      complete: function () {
        isLoading = false;
        $grid.removeClass('ps-grid--loading');
      },
    });

    if (scrollToResults) {
      $('html, body').animate(
        { scrollTop: $('#ps-results-wrap').offset().top - 32 },
        300
      );
    }
  }

  function handleSuccess(response) {
    if (!response.success) {
      showError();
      return;
    }

    const { html, total, pages, paged } = response.data;

    if (!html) {
      $grid.empty();
      $meta.prop('hidden', true);
      $pagination.empty();
      $noResults.prop('hidden', false);
      return;
    }

    // Inject cards with stagger animation
    $grid.html(html);
    $grid.find('.ps-card').each(function (i) {
      $(this).css('animation-delay', i * 60 + 'ms');
    });

    // Results count
    $meta
      .prop('hidden', false)
      .html(
        '<strong>' + total + '</strong> propert' + (total === 1 ? 'y' : 'ies') + ' found'
      );

    renderPagination(pages, paged);
    $noResults.prop('hidden', true);
  }

  function handleError() {
    showError();
  }

  function showError() {
    $grid.html(
      '<p class="ps-error">Something went wrong. Please try again.</p>'
    );
  }

  // ── Pagination builder ─────────────────────────────────────────────────────
  function renderPagination(total, current) {
    if (total <= 1) {
      $pagination.empty();
      return;
    }

    let html = '';
    const delta = 2;

    html += '<button class="ps-page-btn ps-page-btn--prev' +
      (current <= 1 ? ' is-disabled' : '') + '" data-page="' +
      (current - 1) + '" ' + (current <= 1 ? 'disabled' : '') +
      ' aria-label="Previous page">&#8592;</button>';

    for (let p = 1; p <= total; p++) {
      if (
        p === 1 ||
        p === total ||
        (p >= current - delta && p <= current + delta)
      ) {
        html += '<button class="ps-page-btn' +
          (p === current ? ' is-active' : '') +
          '" data-page="' + p + '">' + p + '</button>';
      } else if (
        p === current - delta - 1 ||
        p === current + delta + 1
      ) {
        html += '<span class="ps-page-ellipsis">&hellip;</span>';
      }
    }

    html += '<button class="ps-page-btn ps-page-btn--next' +
      (current >= total ? ' is-disabled' : '') + '" data-page="' +
      (current + 1) + '" ' + (current >= total ? 'disabled' : '') +
      ' aria-label="Next page">&#8594;</button>';

    $pagination.html(html);
  }

  // ── Run search on page load ────────────────────────────────────────────────
  doSearch();

})(jQuery);

/* ── Featured Properties Slider ───────────────────────────────────────────── */
(function () {
  'use strict';

  function initSlider(slider) {
    var track      = slider.querySelector('.fp-track');
    var slides     = slider.querySelectorAll('.fp-slide');
    var prevBtn    = slider.querySelector('.fp-btn--prev');
    var nextBtn    = slider.querySelector('.fp-btn--next');
    var dotsWrap   = slider.querySelector('.fp-dots');
    var autoplay   = slider.dataset.autoplay === 'true';
    var total      = slides.length;
    var current    = 0;
    var timer      = null;
    var visCount   = getVisibleCount();

    // Build dots — one per slideable position, not per card
    var dotCount = Math.max(1, total - getVisibleCount() + 1);
    for (var i = 0; i < dotCount; i++) {
      var dot = document.createElement('button');
      dot.className = 'fp-dot' + (i === 0 ? ' is-active' : '');
      dot.setAttribute('aria-label', 'Go to slide ' + (i + 1));
      dot.dataset.index = i;
      dotsWrap.appendChild(dot);
    }

    var dots = dotsWrap.querySelectorAll('.fp-dot');
    function getVisibleCount() {
      var w = window.innerWidth;
      if (w <= 560) return 1;
      if (w <= 900) return 2;
      return 3;
    }

    function getMaxIndex() {
      return Math.max(0, total - getVisibleCount());
    }

    function goTo(index) {
      current = Math.max(0, Math.min(index, getMaxIndex()));
      var slideWidth = slides[0].offsetWidth + 24; // width + gap
      track.style.transform = 'translateX(-' + (current * slideWidth) + 'px)';

      dots.forEach(function (d, i) {
        d.classList.toggle('is-active', i === current);
      });
    }

    function next() { goTo(current >= getMaxIndex() ? 0 : current + 1); }
    function prev() { goTo(current <= 0 ? getMaxIndex() : current - 1); }

    if (prevBtn) prevBtn.addEventListener('click', function () { prev(); resetTimer(); });
    if (nextBtn) nextBtn.addEventListener('click', function () { next(); resetTimer(); });

    dots.forEach(function (dot) {
      dot.addEventListener('click', function () {
        goTo(parseInt(this.dataset.index, 10));
        resetTimer();
      });
    });

    // Touch swipe
    var touchStartX = null;
    track.addEventListener('touchstart', function (e) {
      touchStartX = e.changedTouches[0].clientX;
    }, { passive: true });
    track.addEventListener('touchend', function (e) {
      if (touchStartX === null) return;
      var diff = touchStartX - e.changedTouches[0].clientX;
      if (Math.abs(diff) > 40) { diff > 0 ? next() : prev(); resetTimer(); }
      touchStartX = null;
    }, { passive: true });

    // Autoplay
    function startTimer() {
      if (!autoplay) return;
      timer = setInterval(next, 4000);
    }

    function resetTimer() {
      clearInterval(timer);
      startTimer();
    }

    // Recalculate on resize — rebuild dots in case breakpoint changed
    window.addEventListener('resize', function () {
      dotsWrap.innerHTML = '';
      var newDotCount = Math.max(1, total - getVisibleCount() + 1);
      for (var i = 0; i < newDotCount; i++) {
        var d = document.createElement('button');
        d.className = 'fp-dot' + (i === current ? ' is-active' : '');
        d.setAttribute('aria-label', 'Go to slide ' + (i + 1));
        d.dataset.index = i;
        d.addEventListener('click', function () { goTo(parseInt(this.dataset.index, 10)); resetTimer(); });
        dotsWrap.appendChild(d);
      }
      dots = dotsWrap.querySelectorAll('.fp-dot');
      goTo(current);
    });

    startTimer();
  }

  // Init all sliders on page
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.fp-slider').forEach(initSlider);
  });

})();

/* ── Featured Property Hero Slideshow ────────────────────────────────────── */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.fph-hero').forEach(function (hero) {
      var bgs = hero.querySelectorAll('.fph-bg');
      if (bgs.length <= 1) return;

      var current = 0;

      setInterval(function () {
        bgs[current].classList.remove('is-active');
        current = (current + 1) % bgs.length;
        bgs[current].classList.add('is-active');
      }, 4000);
    });
  });

})();

/* ── Featured Properties Hero Group Slider ───────────────────────────────── */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.fph-group').forEach(function (group) {
      var slides = group.querySelectorAll('.fph-group-slide');
      var dots   = group.querySelectorAll('.fph-group-dot');
      if (slides.length <= 1) return;

      var current = 0;

      function goTo(index) {
        slides[current].classList.remove('is-active');
        dots[current] && dots[current].classList.remove('is-active');
        current = (index + slides.length) % slides.length;
        slides[current].classList.add('is-active');
        dots[current] && dots[current].classList.add('is-active');

        // Also reset that slide's background image fadeshow
        var bgs = slides[current].querySelectorAll('.fph-bg');
        bgs.forEach(function (bg) { bg.classList.remove('is-active'); });
        if (bgs[0]) bgs[0].classList.add('is-active');
      }

      // Dot clicks
      dots.forEach(function (dot) {
        dot.addEventListener('click', function () {
          goTo(parseInt(this.dataset.index, 10));
          resetTimer();
        });
      });

      // Per-slide background image autofade
      slides.forEach(function (slide) {
        var bgs = slide.querySelectorAll('.fph-bg');
        if (bgs.length <= 1) return;
        var bgCurrent = 0;
        setInterval(function () {
          bgs[bgCurrent].classList.remove('is-active');
          bgCurrent = (bgCurrent + 1) % bgs.length;
          bgs[bgCurrent].classList.add('is-active');
        }, 4000);
      });

      // Autoslide between properties every 6 seconds
      var timer = setInterval(function () { goTo(current + 1); }, 6000);

      function resetTimer() {
        clearInterval(timer);
        timer = setInterval(function () { goTo(current + 1); }, 6000);
      }
    });
  });

})();
