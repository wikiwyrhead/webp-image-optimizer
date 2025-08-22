/* global jQuery, wioAdmin */
(function($){
  $(function(){
    // Help card toggle with persistence
    var $btn = $('#wio-help-toggle');
    var $content = $('#wio-help-content');
    if (typeof wioAdmin !== 'undefined' && $btn.length && $content.length) {
      var showLabel = (wioAdmin.labels && wioAdmin.labels.show) || 'Show';
      var hideLabel = (wioAdmin.labels && wioAdmin.labels.hide) || 'Hide';
      var isOpen = !!wioAdmin.is_open;

      function render(){
        $content.toggle(isOpen);
        $btn.attr('aria-expanded', isOpen ? 'true' : 'false');
        $btn.text(isOpen ? hideLabel : showLabel);
      }

      render();

      $btn.on('click', function(){
        isOpen = !isOpen;
        render();
        // Persist state per user
        $.post(wioAdmin.ajax_url, {
          action: 'wio_save_help_state',
          nonce: wioAdmin.nonce,
          open: isOpen ? '1' : '0'
        });
      });
    }

    // Learn more modal (inline docs)
    var $modal = $('#wio-modal');
    var $docsBody = $('#wio-modal-docs');
    var $openNew = $('#wio-open-new');
    var $navLinks = $('.wio-docs__link');
    var $sections = $docsBody.find('h4[id]');
    var currentSection = '';

    function setActive(sectionId){
      if (!$navLinks.length) return;
      $navLinks.removeClass('is-active');
      if (sectionId) {
        $navLinks.filter('[data-section="' + sectionId + '"]').addClass('is-active');
      }
    }

    function scrollToSection(sectionId){
      if (!sectionId) return;
      var $target = $docsBody.find('[id="' + sectionId + '"]');
      if ($target.length) {
        var top = $target.position().top + $docsBody.scrollTop();
        $docsBody.stop(true).animate({ scrollTop: Math.max(0, top - 8) }, 200);
        setActive(sectionId);
      }
    }

    function openModal(section){
      if (!$modal.length) return;
      currentSection = section || '';
      var gh = 'https://github.com/wikiwyrhead/webp-image-optimizer' + (currentSection ? '#' + currentSection : '');
      $openNew.attr('href', gh);
      $modal.attr('aria-hidden', 'false').show();
      $('body').addClass('wio-modal-open');
      // Focus and esc
      setTimeout(function(){ $modal.find('.wio-modal__close').trigger('focus'); }, 0);
      $(document).on('keydown.wioModal', function(evt){ if (evt.key === 'Escape') closeModal(); });
      // Scroll to section if any
      if (currentSection) {
        // ensure at top first for consistent positions
        $docsBody.scrollTop(0);
        scrollToSection(currentSection);
      } else {
        setActive($sections.first().attr('id'));
      }
      // ScrollSpy within modal (robust even with short content)
      $docsBody.off('scroll.wioSpy');
      var isScrollable = $docsBody[0] && ($docsBody[0].scrollHeight > $docsBody[0].clientHeight);
      if (!isScrollable) {
        // No scroll area; just keep the selected link active
        setActive(currentSection || ($sections.first().attr('id') || ''));
        return;
      }
      $docsBody.on('scroll.wioSpy', function(){
        var bestId = $sections.first().attr('id');
        var bestTop = Infinity;
        $sections.each(function(){
          var $s = $(this);
          var top = Math.abs($s.position().top); // distance from top of container
          if (top < bestTop) {
            bestTop = top;
            bestId = $s.attr('id');
          }
        });
        if (bestId && bestId !== currentSection) {
          currentSection = bestId;
          setActive(bestId);
        }
      });
    }

    function closeModal(){
      if (!$modal.length) return;
      $modal.attr('aria-hidden', 'true').hide();
      $('body').removeClass('wio-modal-open');
      $(document).off('keydown.wioModal');
      currentSection = '';
    }

    $(document).on('click', '.wio-learn-more', function(e){
      // Allow new tab/window via modifier keys
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.which === 2) {
        return; // let browser handle
      }
      var section = $(this).data('section');
      e.preventDefault();
      openModal(section);
    });

    // In-modal nav links
    $(document).on('click', '.wio-docs__link', function(e){
      e.preventDefault();
      var section = $(this).data('section');
      if (section) {
        currentSection = section;
        scrollToSection(section);
      }
    });

    $(document).on('click', '#wio-modal [data-close] , #wio-modal .wio-modal__backdrop', function(){
      closeModal();
    });
  });
})(jQuery);
