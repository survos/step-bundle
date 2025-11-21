// file: assets/src/controllers/reveal_controller.js — Stimulus controller for Reveal slides + HUD

import { Controller } from '@hotwired/stimulus';

import Reveal from 'reveal.js';
//import 'reveal.js/dist/reveal.css';
import RevealHighlight from 'reveal.js/plugin/highlight/highlight.js';
import RevealNotes from 'reveal.js/plugin/notes/notes.js';

export default class extends Controller {
  static values = {
    code: String,
    jsonUrl: String,
    theme: { type: String, default: 'light' }
  }

  static targets = [
    'codeLabel', 'stepLabel', 'jsonDeckLink', 'jsonSlideLink', 'copyButton'
  ]

  deck = null;

  connect() {
    console.log("hello from " + this.identifier);
    // optional theme switch (via class on <html>)
    document.documentElement.dataset.revealTheme = this.themeValue;

    this.deck = new Reveal(this.element, {
      hash: true,
      plugins: [ RevealHighlight, RevealNotes ],
      transition: 'slide',
      controls: true,
      progress: true,
      center: false,
      width: '100%',
      height: '100%',
      margin: 0,
      minScale: 1,
      maxScale: 1
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

    window.Reveal = this.deck;
  }

  disconnect() {
    if (this.deck) this.deck.destroy();
  }

  /* -------------------- helpers -------------------- */

  _currentIndex() {
    const idx = this.deck?.getIndices().h ?? 0;
    return Math.max(0, Math.min(idx, (this.slidesValue?.length ?? 0) - 1));
  }

// remove every reference to this.slidesValue / slidesValue / slides
// _currentSlide() and HUD updates can instead read from the DOM directly:
  _currentSlide() {
    const idx = this.deck?.getIndices().h ?? 0;
    const sections = this.element.querySelectorAll('.slides section');
    const sec = sections[idx] || null;
    const title = sec?.querySelector('.slide-title')?.textContent?.trim() ?? '';
    const task  = sec?.dataset.taskName ?? '';
    return { title, task, idx };
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
    const { title, task, idx } = this._currentSlide();
    if (this.hasStepLabelTarget) this.stepLabelTarget.textContent = title || `step-${idx+1}`;
    if (this.hasCopyButtonTarget) {
      const code = this.codeValue || 'deck';
      this.copyButtonTarget.dataset.cmd = `CODE=${code} castor ${task}`;
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
