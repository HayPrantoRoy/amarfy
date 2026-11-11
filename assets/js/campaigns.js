/* Create / edit marketing campaigns (used on Growth pages). */
window.campaignModal = function (c = {}) {
  const {modal, api, esc, toast} = UI;
  const CH = {email: 'Email', social: 'Social media', search: 'Search ads', sms: 'SMS', affiliate: 'Affiliate', other: 'Other'};
  const m = modal({title: c.id ? 'Edit campaign' : 'Create campaign', size: 'wide', body: `
    <label class="field"><span>Campaign name</span><input data-f="name" value="${esc(c.name || '')}" placeholder="Eid sale 2026"></label>
    <div class="row2"><label class="field"><span>Channel</span><select class="input" data-f="channel">${Object.entries(CH).map(([k, v]) => `<option value="${k}" ${k === c.channel ? 'selected' : ''}>${v}</option>`).join('')}</select></label>
      <label class="field"><span>Status</span><select class="input" data-f="status">${['draft', 'active', 'paused', 'completed'].map(s => `<option value="${s}" ${s === (c.status || 'draft') ? 'selected' : ''}>${s[0].toUpperCase() + s.slice(1)}</option>`).join('')}</select></label></div>
    <label class="field"><span>Goal</span><input data-f="goal" value="${esc(c.goal || '')}" placeholder="Grow repeat purchases"></label>
    <div class="row3"><label class="field"><span>Budget (${esc(window.AMARFY.cur)})</span><input type="number" min="0" step="0.01" data-f="budget" value="${c.budget || ''}"></label>
      <label class="field"><span>Start date</span><input type="date" data-f="starts_on" value="${esc(c.starts_on || '')}"></label>
      <label class="field"><span>End date</span><input type="date" data-f="ends_on" value="${esc(c.ends_on || '')}"></label></div>
    <label class="field"><span>UTM campaign</span><input data-f="utm_campaign" value="${esc(c.utm_campaign || '')}" placeholder="auto-generated from the name"><small>Orders arriving with this utm_campaign are attributed to the campaign.</small></label>`,
    footer: `${c.id ? '<button class="btn plain left" data-del style="color:#c70a24">Delete campaign</button>' : ''}<button class="btn" data-close>Cancel</button><button class="btn primary" data-ok>${c.id ? 'Save' : 'Create campaign'}</button>`});
  m.$('[data-ok]').onclick = async () => {
    const d = {id: c.id || 0}; m.$$('[data-f]').forEach(i => d[i.dataset.f] = i.value);
    await api('growth/campaign_save', d); m.close(); toast(c.id ? 'Campaign saved' : 'Campaign created'); setTimeout(() => location.reload(), 300);
  };
  const del = m.$('[data-del]');
  if (del) del.onclick = async () => { await api('growth/campaign_delete', {id: c.id}); m.close(); location.reload(); };
};
