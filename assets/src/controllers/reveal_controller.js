// file: assets/src/controllers/reveal_controller.js — Stimulus controller for Reveal slides + HUD

import { Controller } from '@hotwired/stimulus';
import Reveal from 'reveal.js';
import 'reveal.js/dist/reveal.css';
import RevealHighlight from 'reveal.js/plugin/highlight/highlight.js';

export default class extends Controller {
  static values = {
    code: String,
    slides: Array,
    jsonUrl: String,
    theme: { type: String, default: 'black' }
  }

  static targets = [
    'codeLabel', 'stepLabel', 'jsonDeckLink', 'jsonSlideLink', 'copyButton'
  ]

  deck = null;

  connect() {
    // optional theme switch (via class on <html>)
    document.documentElement.dataset.revealTheme = this.themeValue;

    this.deck = new Reveal(this.element, {
      hash: true,
      plugins: [ RevealHighlight ],
      transition: 'slide',
      controls: true,
      progress: true,
      center: false,
      margin: 0.02,
      minScale: 0.2,
      maxScale: 1.6
    });

    this.deck.initialize().then(() => {
      this._updateHud();
      this._autoFit();

      this.deck.on('slidechanged', () => {
        this._autoFit();
        this._updateHud();
      });
      window.addEventListener('resize', () => {
        window.setTimeout(() => { this._autoFit(); this._updateHud(); }, 60);
      });
    });
  }

  disconnect() {
    if (this.deck) this.deck.destroy();
  }

  /* -------------------- helpers -------------------- */

  _currentIndex() {
    const idx = this.deck?.getIndices().h ?? 0;
    return Math.max(0, Math.min(idx, (this.slidesValue?.length ?? 0) - 1));
  }

  _currentSlide() {
    const i = this._currentIndex();
    const s = Array.isArray(this.slidesValue) ? this.slidesValue[i] : null;
    return { obj: s, idx: i };
  }

  _autoFit() {
    const sec = this.element.querySelector('.slides section.present');
    const content = sec?.querySelector('.slide-content');
    if (!sec || !content) return;
    sec.classList.remove('compact-1','compact-2','compact-3');
    let i = 0;
    while (content.scrollHeight > content.clientHeight && i < 3) {
      i++; sec.classList.add('compact-'+i);
    }
  }

  async _updateHud() {
    const code = this.codeValue || 'deck';
    const { obj, idx } = this._currentSlide();
    const title = obj?.title || (idx === 0 ? 'intro' : `step-${idx+1}`);

    if (this.hasCodeLabelTarget) this.codeLabelTarget.textContent = code;
    if (this.hasStepLabelTarget) this.stepLabelTarget.textContent = title;

    // JSON (deck)
    if (this.hasJsonDeckLinkTarget) {
      if (this.jsonUrlValue) {
        this.jsonDeckLinkTarget.href = this.jsonUrlValue;
        this.jsonDeckLinkTarget.style.display = '';
      } else {
        this.jsonDeckLinkTarget.style.display = 'none';
      }
    }

    // JSON (current slide)
    if (this.hasJsonSlideLinkTarget) {
      if (obj) {
        const payload = { code, index: idx, slide: obj };
        const url = URL.createObjectURL(new Blob([JSON.stringify(payload, null, 2)], {type:'application/json'}));
        this.jsonSlideLinkTarget.href = url;
        this.jsonSlideLinkTarget.download = `${code}-${title}.json`;
        this.jsonSlideLinkTarget.style.display = '';
      } else {
        this.jsonSlideLinkTarget.removeAttribute('href');
        this.jsonSlideLinkTarget.style.display = 'none';
      }
    }

    // Copy run cmd
    if (this.hasCopyButtonTarget) {
      const taskName = obj?.task_name ?? '';
      const cmd = `CODE=${code} castor ${taskName}`;
      this.copyButtonTarget.dataset.cmd = cmd;
    }
  }

  copy() {
    const cmd = this.copyButtonTarget?.dataset?.cmd || '';
    if (!cmd) return;
    navigator.clipboard.writeText(cmd).then(() => {
      const old = this.copyButtonTarget.textContent;
      this.copyButtonTarget.textContent = 'Copied!';
      setTimeout(() => this.copyButtonTarget.textContent = old, 900);
    }).catch(() => {
      const ta = document.createElement('textarea');
      ta.value = cmd; document.body.appendChild(ta);
      ta.select(); document.execCommand('copy'); ta.remove();
    });
  }
}
