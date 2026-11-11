<?php
$PAGE_TITLE = 'Shipping labels';
echo topbar('label', [['Shipping labels', null]]);
$unfulfilled = (int)t_val("SELECT COUNT(*) FROM orders WHERE fulfillment_status='unfulfilled' AND cancelled_at IS NULL");
?>
<div class="page">
  <div class="card" style="width:min(1150px,100%);margin:20px auto 0;border-radius:18px">
    <div class="promo">
      <div>
        <div class="carriers"><span style="background:#e31837">➤</span><span style="background:#ffcc00;color:#d40511">DHL</span><span style="background:#4d148c">FedEx</span><span style="background:#1b4f9c">✓</span></div>
        <h2>Everything you need to ship,<br>included in your plan</h2>
        <ul>
          <li><?= icon('discounts') ?>Discounted shipping rates with top carriers</li>
          <li><?= icon('channels') ?>Orders from every sales channel in one place</li>
          <li><?= icon('orders') ?>Pick, pack, and ship up to 250 orders at once</li>
          <li><?= icon('barcode') ?>Scan items to verify before they ship</li>
          <li><?= icon('pin') ?>Tracking sent to customers automatically</li>
        </ul>
        <div style="display:flex;gap:10px">
          <a class="btn primary" style="height:34px;border-radius:12px" href="<?= h(url('orders?view=unfulfilled')) ?>">Buy labels<?= $unfulfilled ? ' (' . $unfulfilled . ')' : '' ?></a>
          <button class="chip-btn" type="button" id="calcRates">Calculate rates</button>
        </div>
      </div>
      <div style="display:flex;justify-content:center">
        <svg viewBox="0 0 440 340" style="width:100%;max-width:440px">
          <defs><radialGradient id="glow" cx=".45" cy=".45" r=".55"><stop offset="0" stop-color="#fdf6c8"/><stop offset=".55" stop-color="#d9f4ec"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></radialGradient></defs>
          <ellipse cx="220" cy="170" rx="210" ry="165" fill="url(#glow)"/>
          <path d="M70 200l170-40 140 40-170 50z" fill="#c9965c"/><path d="M70 200v80l130 50V250z" fill="#b07f48"/><path d="M200 250v80l180-60v-70z" fill="#d6a76b"/>
          <rect x="230" y="262" width="80" height="58" rx="3" fill="#fff" transform="skewY(-12)"/><path d="M90 268h40v30H90z" fill="#fff" opacity=".7"/>
          <path d="M130 120l120-25 80 25-120 30z" fill="#d6a76b"/><path d="M130 120v55l80 25v-50z" fill="#b07f48"/><path d="M210 150v50l120-35v-45z" fill="#c9965c"/>
          <rect x="170" y="45" width="130" height="70" rx="22" fill="#2f3438"/><rect x="190" y="95" width="70" height="44" fill="#fff" transform="rotate(-8 225 117)"/>
          <path d="M330 255l70-15 30 15-70 18z" fill="#d6a76b"/><path d="M330 255v38l30 12v-34z" fill="#b07f48"/><path d="M360 271v34l70-22v-28z" fill="#c9965c"/>
        </svg>
      </div>
    </div>
  </div>
  <?= learn_more('buying shipping labels') ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('calcRates').onclick = () => {
    const m = UI.modal({title: 'Calculate shipping rates', body: `<div class="row2"><label class="field"><span>From</span><input value="Dhaka, Bangladesh" data-from></label><label class="field"><span>To</span><input placeholder="City, country" data-to></label></div>
      <div class="row3"><label class="field"><span>Weight (kg)</span><input type="number" min="0" step="0.1" value="1" data-w></label><label class="field"><span>Length (cm)</span><input type="number" value="20"></label><label class="field"><span>Width (cm)</span><input type="number" value="15"></label></div><div data-out></div>`,
      footer: '<button class="btn" data-close>Close</button><button class="btn primary" data-go>Get rates</button>'});
    m.$('[data-go]').onclick = () => {
      const w = Math.max(.1, parseFloat(m.$('[data-w]').value) || 1), intl = !/bangladesh|dhaka|chattogram|sylhet|khulna|rajshahi/i.test(m.$('[data-to]').value || 'Bangladesh');
      const rates = intl ? [['DHL Express', 2800 + w * 900], ['FedEx International', 2600 + w * 850]] : [['Inside Dhaka', 60 + w * 15], ['Outside Dhaka', 120 + w * 20], ['Express', 200 + w * 30]];
      m.$('[data-out]').innerHTML = '<table class="grid"><thead><tr><th>Service</th><th class="num">Estimated rate</th></tr></thead><tbody>' + rates.map(r => `<tr><td>${r[0]}</td><td class="num">${UI.money(r[1], true)}</td></tr>`).join('') + '</tbody></table>';
    };
  };
});
</script>
