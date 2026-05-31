<?php
/**
 * Shop Page - category tabs, items grid with rarity, purchase
 */
?>
<div class="container">
    <h1 class="mb-16">🏪 Do'kon</h1>

    <!-- Category tabs -->
    <div class="tabs" id="shopTabs">
        <div class="tab active" data-category="0" onclick="loadShopCategory(0, this)">⭐ Tavsiya</div>
        <div class="tab" data-category="1" onclick="loadShopCategory(1, this)">🪨 Toshlar</div>
        <div class="tab" data-category="2" onclick="loadShopCategory(2, this)">🏁 Doskalar</div>
        <div class="tab" data-category="3" onclick="loadShopCategory(3, this)">🖼️ Ramkalar</div>
        <div class="tab" data-category="4" onclick="loadShopCategory(4, this)">✨ Effektlar</div>
        <div class="tab" data-category="6" onclick="loadShopCategory(6, this)">🏆 Unvonlar</div>
    </div>

    <!-- Items grid -->
    <div class="shop-grid" id="shopGrid">
        <div class="caption text-center">Yuklanmoqda...</div>
    </div>
</div>

<script>
let shopLoaded = false;

window.App.onPageLoad = (function(prev) {
  return function(page) {
    if (typeof prev === 'function') prev(page);
    if (page === 'shop' && !shopLoaded) {
      loadShopCategory(0, null);
      shopLoaded = true;
    }
  };
})(window.App.onPageLoad);

async function loadShopCategory(categoryId, tabEl) {
  // Update active tab
  if (tabEl) {
    document.querySelectorAll('#shopTabs .tab').forEach(t => t.classList.remove('active'));
    tabEl.classList.add('active');
  }

  const grid = document.getElementById('shopGrid');
  grid.innerHTML = '<div class="caption text-center">Yuklanmoqda...</div>';

  try {
    const path = categoryId > 0 ? '/shop/items?category_id=' + categoryId : '/shop/items';
    const items = await window.App.api.get(path);

    if (!items || !items.length) {
      grid.innerHTML = '<div class="caption text-center">Buyumlar yo\'q</div>';
      return;
    }

    grid.innerHTML = items.map(item => renderShopItem(item)).join('');
  } catch (e) {
    grid.innerHTML = '<div class="caption text-center">Xatolik: ' + e.message + '</div>';
  }
}

function renderShopItem(item) {
  const priceIcon = item.price_type === 'diamonds' ? '💎' :
                    (item.price_type === 'coins' ? '🪙' :
                    (item.price_type === 'free' ? '' : '⭐'));
  const priceText = item.price_type === 'free' ? 'Bepul' :
                    (item.price_type === 'vip_only' ? 'VIP' : item.price + ' ' + priceIcon);
  const owned = item.owned ? '<span class="owned-badge">✓ Bor</span>' : '';
  const img = item.image_url || '/images/item-placeholder.png';

  return '<div class="shop-item rarity-' + item.rarity + '">' + owned +
    '<img class="item-img" src="' + img + '" alt="' + item.name + '" onerror="this.style.opacity=0.3">' +
    '<div class="item-name">' + item.name + '</div>' +
    (item.owned ? '' :
      '<button class="price-badge" onclick="purchaseItem(' + item.id + ')">' + priceText + '</button>') +
    '</div>';
}

async function purchaseItem(itemId) {
  if (!confirm('Bu buyumni sotib olasizmi?')) return;
  try {
    const res = await window.App.api.post('/shop/purchase', { item_id: itemId });
    window.App.toast('Buyum sotib olindi!', 'success');
    if (window.App.user) {
      window.App.user.diamonds = res.diamonds;
      window.App.user.coins = res.coins;
      window.App.renderUserHeader();
    }
    // Reload current category
    const activeTab = document.querySelector('#shopTabs .tab.active');
    loadShopCategory(activeTab ? parseInt(activeTab.dataset.category) : 0, activeTab);
  } catch (e) {
    window.App.toast(e.message, 'error');
  }
}
</script>
