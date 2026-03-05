/* global jQuery, wioAdmin */
(function($){
  'use strict';
  $(function(){

    /* ── Range sliders: sync output labels ─────────────────── */
    $('input[type="range"]').on('input', function(){
      var $out = $(this).siblings('output');
      if ($out.length) $out.text(this.value);
    });

    /* ── Help card toggle with persistence ─────────────────── */
    var $helpBtn = $('#wio-help-toggle');
    var $helpContent = $('#wio-help-content');
    if (typeof wioAdmin !== 'undefined' && $helpBtn.length && $helpContent.length) {
      var showLabel = (wioAdmin.labels && wioAdmin.labels.show) || 'Show';
      var hideLabel = (wioAdmin.labels && wioAdmin.labels.hide) || 'Hide';
      var isOpen = !!wioAdmin.is_open;

      function renderHelp(){
        $helpContent.toggle(isOpen);
        $helpBtn.attr('aria-expanded', isOpen ? 'true' : 'false').text(isOpen ? hideLabel : showLabel);
      }
      renderHelp();

      $helpBtn.on('click', function(){
        isOpen = !isOpen;
        renderHelp();
        $.post(wioAdmin.ajax_url, {
          action: 'wio_save_help_state',
          nonce: wioAdmin.nonce,
          open: isOpen ? '1' : '0'
        });
      });
    }

    /* ── Bulk conversion ───────────────────────────────────── */
    var bulkRunning = false;
    var bulkStopped = false;
    var $startBtn  = $('#wio-bulk-start');
    var $stopBtn   = $('#wio-bulk-stop');
    var $progress  = $('#wio-progress');
    var $fill      = $('#wio-progress-fill');
    var $pText     = $('#wio-progress-text');
    var $log       = $('#wio-bulk-log');
    var $logList   = $('#wio-bulk-log-entries');
    var $eligible  = $('#wio-bulk-eligible');
    var $done      = $('#wio-bulk-done');
    var $skipped   = $('#wio-bulk-skipped');
    var $saved     = $('#wio-bulk-saved');

    function humanSize(bytes){
      if (bytes < 1024) return bytes + ' B';
      if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
      return (bytes / 1048576).toFixed(2) + ' MB';
    }

    function addLog(msg, cls){
      var span = $('<div>').addClass(cls || '').text(msg);
      $logList.append(span);
      $logList.scrollTop($logList[0].scrollHeight);
    }

    // Fetch eligible IDs on bulk tab load
    if ($startBtn.length && typeof wioAdmin !== 'undefined') {
      $.post(wioAdmin.ajax_url, {
        action: 'wio_bulk_status',
        nonce: wioAdmin.nonce
      }, function(res){
        if (res.success) {
          $eligible.text(res.data.ids.length);
          $startBtn.data('ids', res.data.ids);
        }
      });
    }

    $startBtn.on('click', function(){
      var ids = $(this).data('ids');
      if (!ids || !ids.length) {
        $pText.text(wioAdmin.labels.no_images || 'No eligible images found.');
        $progress.show();
        return;
      }
      bulkRunning = true;
      bulkStopped = false;
      $startBtn.prop('disabled', true);
      $stopBtn.show();
      $progress.show();
      $log.show();
      $logList.empty();

      var total = ids.length;
      var doneCount = 0;
      var skipCount = 0;
      var totalSaved = 0;

      function next(i){
        if (bulkStopped || i >= total) {
          finish();
          return;
        }
        var pct = Math.round(((i) / total) * 100);
        $fill.css('width', pct + '%');
        $pText.text((wioAdmin.labels.converting || 'Converting') + ' ' + (i + 1) + ' / ' + total + '…');

        $.post(wioAdmin.ajax_url, {
          action: 'wio_bulk_convert',
          nonce: wioAdmin.nonce,
          attachment_id: ids[i]
        }, function(res){
          if (res.success) {
            doneCount++;
            totalSaved += (res.data.bytes_saved || 0);
            addLog('#' + ids[i] + ' — ' + (res.data.message || 'OK'), 'wio-log-ok');
          } else {
            skipCount++;
            addLog('#' + ids[i] + ' — ' + (res.data && res.data.message ? res.data.message : 'Skipped'), 'wio-log-skip');
          }
          $done.text(doneCount);
          $skipped.text(skipCount);
          $saved.text(humanSize(totalSaved));
          next(i + 1);
        }).fail(function(){
          skipCount++;
          addLog('#' + ids[i] + ' — Request failed', 'wio-log-err');
          $skipped.text(skipCount);
          next(i + 1);
        });
      }

      function finish(){
        bulkRunning = false;
        $fill.css('width', '100%');
        $pText.text(bulkStopped ? (wioAdmin.labels.stopped || 'Stopped.') : (wioAdmin.labels.complete || 'Done!') + ' ' + doneCount + ' converted, ' + skipCount + ' skipped.');
        $startBtn.prop('disabled', false);
        $stopBtn.hide();
        addLog('─── ' + (bulkStopped ? 'Stopped' : 'Complete') + ' ───', '');
      }

      next(0);
    });

    $stopBtn.on('click', function(){
      bulkStopped = true;
    });

    /* ── Docs modal ────────────────────────────────────────── */
    var $modal = $('#wio-modal');
    var $docsBody = $('#wio-modal-docs');
    var $navLinks = $('.wio-docs__link');
    var $sections = $docsBody.find('h4[id]');
    var currentSection = '';

    function setActive(sid){
      $navLinks.removeClass('is-active');
      if (sid) $navLinks.filter('[data-section="' + sid + '"]').addClass('is-active');
    }

    function scrollToSection(sid){
      if (!sid) return;
      var $t = $docsBody.find('[id="' + sid + '"]');
      if ($t.length) {
        $docsBody.stop(true).animate({ scrollTop: $t.position().top + $docsBody.scrollTop() - 8 }, 200);
        setActive(sid);
      }
    }

    function openModal(section){
      if (!$modal.length) return;
      currentSection = section || '';
      $('#wio-open-new').attr('href', 'https://github.com/wikiwyrhead/webp-image-optimizer' + (currentSection ? '#' + currentSection : ''));
      $modal.attr('aria-hidden', 'false').show();
      $('body').addClass('wio-modal-open');
      setTimeout(function(){ $modal.find('.wio-modal__close').trigger('focus'); }, 0);
      $(document).on('keydown.wioModal', function(e){ if (e.key === 'Escape') closeModal(); });
      if (currentSection) {
        $docsBody.scrollTop(0);
        scrollToSection(currentSection);
      } else {
        setActive($sections.first().attr('id'));
      }
      $docsBody.off('scroll.wioSpy');
      if ($docsBody[0] && $docsBody[0].scrollHeight > $docsBody[0].clientHeight) {
        $docsBody.on('scroll.wioSpy', function(){
          var best = $sections.first().attr('id'), bestD = Infinity;
          $sections.each(function(){
            var d = Math.abs($(this).position().top);
            if (d < bestD) { bestD = d; best = this.id; }
          });
          if (best !== currentSection) { currentSection = best; setActive(best); }
        });
      }
    }

    function closeModal(){
      $modal.attr('aria-hidden', 'true').hide();
      $('body').removeClass('wio-modal-open');
      $(document).off('keydown.wioModal');
      currentSection = '';
    }

    $(document).on('click', '.wio-learn-more', function(e){
      if (e.metaKey || e.ctrlKey || e.shiftKey) return;
      e.preventDefault();
      openModal($(this).data('section'));
    });
    $(document).on('click', '.wio-docs__link', function(e){
      e.preventDefault();
      var s = $(this).data('section');
      if (s) { currentSection = s; scrollToSection(s); }
    });
    $(document).on('click', '#wio-modal [data-close], #wio-modal .wio-modal__backdrop', closeModal);

    /* ── AI Alt Text toggle & test connection ───────────────── */
    var $aiToggle = $('#wio-ai-toggle');
    var $aiFields = $('#wio-ai-fields');
    var $aiProvider = $('#wio-ai-provider');
    var $aiModel = $('#wio-ai-model');

    var defaultModels = {
      nvidia: 'google/gemma-3-27b-it',
      anthropic: 'claude-sonnet-4-20250514',
      openai: 'gpt-4o-mini',
      xai: 'grok-2-vision-latest',
      mistral: 'pixtral-large-latest',
      google: 'gemini-2.0-flash',
      groq: 'llama-3.2-90b-vision-preview',
      openrouter: 'openrouter/free'
    };

    var keyHelpLinks = {
      nvidia: '<strong>Recommended:</strong> Get a free key at <a href="https://build.nvidia.com/explore/discover" target="_blank" rel="noopener">build.nvidia.com</a> — no credit card required',
      anthropic: 'Get a key at <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener">console.anthropic.com</a>',
      openai: 'Get a key at <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">platform.openai.com</a>',
      xai: 'Get a key at <a href="https://console.x.ai/" target="_blank" rel="noopener">console.x.ai</a>',
      mistral: 'Get a key at <a href="https://console.mistral.ai/api-keys" target="_blank" rel="noopener">console.mistral.ai</a>',
      google: 'Get a key at <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">aistudio.google.com</a>',
      groq: 'Get a free key at <a href="https://console.groq.com/keys" target="_blank" rel="noopener">console.groq.com</a>',
      openrouter: 'Get a key at <a href="https://openrouter.ai/keys" target="_blank" rel="noopener">openrouter.ai/keys</a>'
    };

    var modelHelpTexts = {
      nvidia: 'Default: google/gemma-3-27b-it. Other vision models: microsoft/phi-4-multimodal-instruct, meta/llama-4-maverick-17b-128e-instruct, nvidia/nemotron-nano-12b-v2-vl',
      anthropic: 'Default: claude-sonnet-4-20250514. Other models: claude-haiku-4-20250514',
      openai: 'Default: gpt-4o-mini. Other models: gpt-4o, gpt-4-turbo',
      xai: 'Default: grok-2-vision-latest',
      mistral: 'Default: pixtral-large-latest. Other models: pixtral-12b-2409',
      google: 'Default: gemini-2.0-flash. Other models: gemini-1.5-pro',
      groq: 'Default: llama-3.2-90b-vision-preview. Other models: llama-3.2-11b-vision-preview',
      openrouter: 'Default: openrouter/free (auto-selects a free model)'
    };

    function updateProviderUI() {
      var p = $aiProvider.val();
      $aiModel.attr('placeholder', defaultModels[p] || '');
      $('#wio-ai-key-help').html(keyHelpLinks[p] || '');
      $('#wio-ai-model-help').html(modelHelpTexts[p] || 'Leave empty for the default model.');
    }

    if ($aiToggle.length) {
      $aiToggle.on('change', function(){
        $aiFields.toggle(this.checked);
      });
      $aiProvider.on('change', updateProviderUI);
      updateProviderUI();
    }

    // Test AI connection
    $('#wio-ai-test').on('click', function(){
      var $btn = $(this);
      var $result = $('#wio-ai-test-result');
      $btn.prop('disabled', true);
      $result.removeClass('wio-ai-ok wio-ai-err').text('Testing…');

      $.post(wioAdmin.ajax_url, {
        action: 'wio_test_ai',
        nonce: wioAdmin.nonce,
        provider: $aiProvider.val(),
        api_key: $('#wio-ai-key').val(),
        model: $aiModel.val()
      }, function(res){
        $btn.prop('disabled', false);
        if (res.success) {
          $result.addClass('wio-ai-ok').text('\u2713 ' + res.data.message);
        } else {
          $result.addClass('wio-ai-err').text('\u2717 ' + (res.data && res.data.message ? res.data.message : 'Failed'));
        }
      }).fail(function(){
        $btn.prop('disabled', false);
        $result.addClass('wio-ai-err').text('\u2717 Request failed');
      });
    });

    /* ── Bulk AI Alt Text ──────────────────────────────────── */
    var aiBulkRunning = false;
    var aiBulkStopped = false;
    var $aiStartBtn = $('#wio-ai-bulk-start');
    var $aiStopBtn  = $('#wio-ai-bulk-stop');
    var $aiProgress = $('#wio-ai-progress');
    var $aiFill     = $('#wio-ai-progress-fill');
    var $aiPText    = $('#wio-ai-progress-text');
    var $aiLog      = $('#wio-ai-log');
    var $aiLogList  = $('#wio-ai-log-entries');
    var $aiEligible = $('#wio-ai-eligible');
    var $aiDone     = $('#wio-ai-done');
    var $aiSkipped  = $('#wio-ai-skipped');

    function addAiLog(msg, cls) {
      var span = $('<div>').addClass(cls || '').text(msg);
      $aiLogList.append(span);
      $aiLogList.scrollTop($aiLogList[0].scrollHeight);
    }

    // Fetch missing alt text count on page load
    if ($aiStartBtn.length && typeof wioAdmin !== 'undefined') {
      $.post(wioAdmin.ajax_url, {
        action: 'wio_bulk_alt_status',
        nonce: wioAdmin.nonce
      }, function(res){
        if (res.success) {
          $aiEligible.text(res.data.ids.length);
          $aiStartBtn.data('ids', res.data.ids);
        }
      });
    }

    $aiStartBtn.on('click', function(){
      var ids = $(this).data('ids');
      if (!ids || !ids.length) {
        $aiPText.text('No images with missing alt text found.');
        $aiProgress.show();
        return;
      }
      aiBulkRunning = true;
      aiBulkStopped = false;
      $aiStartBtn.prop('disabled', true);
      $aiStopBtn.show();
      $aiProgress.show();
      $aiLog.show();
      $aiLogList.empty();

      var total = ids.length;
      var doneCount = 0;
      var failCount = 0;

      function nextAi(i){
        if (aiBulkStopped || i >= total) {
          finishAi();
          return;
        }
        var pct = Math.round((i / total) * 100);
        $aiFill.css('width', pct + '%');
        $aiPText.text('Generating ' + (i + 1) + ' / ' + total + '\u2026');

        $.post(wioAdmin.ajax_url, {
          action: 'wio_generate_alt',
          nonce: wioAdmin.nonce,
          attachment_id: ids[i]
        }, function(res){
          if (res.success) {
            doneCount++;
            addAiLog('#' + ids[i] + ' \u2014 ' + (res.data.alt_text || 'OK'), 'wio-log-ok');
          } else {
            failCount++;
            addAiLog('#' + ids[i] + ' \u2014 ' + (res.data && res.data.message ? res.data.message : 'Failed'), 'wio-log-skip');
          }
          $aiDone.text(doneCount);
          $aiSkipped.text(failCount);
          nextAi(i + 1);
        }).fail(function(){
          failCount++;
          addAiLog('#' + ids[i] + ' \u2014 Request failed', 'wio-log-err');
          $aiSkipped.text(failCount);
          nextAi(i + 1);
        });
      }

      function finishAi(){
        aiBulkRunning = false;
        $aiFill.css('width', '100%');
        $aiPText.text(aiBulkStopped ? 'Stopped.' : 'Done! ' + doneCount + ' generated, ' + failCount + ' failed.');
        $aiStartBtn.prop('disabled', false);
        $aiStopBtn.hide();
        addAiLog('\u2500\u2500\u2500 ' + (aiBulkStopped ? 'Stopped' : 'Complete') + ' \u2500\u2500\u2500', '');
      }

      nextAi(0);
    });

    $aiStopBtn.on('click', function(){
      aiBulkStopped = true;
    });

    /* ── Per-image Generate AI Alt Text button (Media Library) ── */
    $(document).on('click', '.wio-generate-alt-btn', function(){
      var $btn = $(this);
      var id = $btn.data('id');
      var $result = $('#wio-alt-result-' + id);
      $btn.prop('disabled', true);
      $result.css('color', '').text('Generating\u2026');

      $.post(wioAdmin.ajax_url, {
        action: 'wio_generate_alt',
        nonce: wioAdmin.nonce,
        attachment_id: id,
        overwrite: 1
      }, function(res){
        $btn.prop('disabled', false);
        if (res.success) {
          $result.css('color', '#46b450').text('\u2713 ' + res.data.alt_text);
          // Update the alt text field if visible
          var $altField = $btn.closest('.compat-item').parent().find('[data-setting="alt"] textarea, [name="_wp_attachment_image_alt"]');
          if ($altField.length) {
            $altField.val(res.data.alt_text).trigger('change');
          }
        } else {
          $result.css('color', '#d63638').text('\u2717 ' + (res.data && res.data.message ? res.data.message : 'Failed'));
        }
      }).fail(function(){
        $btn.prop('disabled', false);
        $result.css('color', '#d63638').text('\u2717 Request failed');
      });
    });

  });
})(jQuery);
