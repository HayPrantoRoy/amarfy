/* Timeline comment box (shared by orders, drafts, POs, transfers, gift cards, customers) */
(function () {
  function bind(root) {
    const ta = root.querySelector('[data-tl-text]'), post = root.querySelector('[data-tl-post]');
    if (!ta) return;
    ta.addEventListener('input', () => { post.disabled = !ta.value.trim(); ta.style.height = 'auto'; ta.style.height = ta.scrollHeight + 'px'; });
    root.querySelectorAll('[data-tl-ins]').forEach(b => b.onclick = () => { ta.value += b.dataset.tlIns; ta.focus(); ta.dispatchEvent(new Event('input')); });
    post.onclick = async () => {
      post.disabled = true;
      const r = await UI.api('timeline/comment', {type: root.dataset.tlType, id: +root.dataset.tlId, message: ta.value});
      const tmp = document.createElement('div'); tmp.innerHTML = r.html;
      const fresh = tmp.firstElementChild; root.replaceWith(fresh); bind(fresh);
    };
  }
  document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('.timeline').forEach(bind));
  window.bindTimeline = bind;
})();
