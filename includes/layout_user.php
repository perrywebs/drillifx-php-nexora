<?php
declare(strict_types=1);
// Shared <head> for user pages — keeps original Tailwind/Font/Signika stack untouched.
function user_head(string $title): void {
    echo '<meta charset="UTF-8">' . "\n";
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">' . "\n";
    echo '<title>' . e($title) . ' - Drillifyx</title>' . "\n";
    echo '<script src="https://cdn.tailwindcss.com"></script>' . "\n";
    echo '<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>' . "\n";
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    echo '<link href="https://fonts.googleapis.com/css2?family=Signika+Negative:wght@300;400;500;600;700&display=swap" rel="stylesheet">' . "\n";
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">' . "\n";
    echo '<script>tailwind.config={theme:{extend:{fontFamily:{sans:["Signika Negative","sans-serif"]},colors:{primary:{50:"#eff6ff",100:"#dbeafe",200:"#bfdbfe",300:"#93c5fd",400:"#60a5fa",500:"#3b82f6",600:"#2563eb",700:"#1d4ed8",800:"#1e40af",900:"#1e3a8a"}}}}}</script>' . "\n";
    echo '<style>body{font-family:"Signika Negative",sans-serif;padding-bottom:70px;background:linear-gradient(180deg,#f8fafc 0%,#eff6ff 100%);min-height:100vh}.bottom-nav-item.active{color:#2563eb;background:linear-gradient(180deg,rgba(37,99,235,.1) 0%,transparent 100%)}.bottom-nav-item.active::before{content:"";position:absolute;top:0;left:50%;transform:translateX(-50%);width:40px;height:3px;background:#2563eb;border-radius:0 0 4px 4px}@keyframes pulse-badge{0%,100%{transform:scale(1)}50%{transform:scale(1.1)}}.badge-pulse{animation:pulse-badge 2s infinite}@keyframes spin-slow{from{transform:rotate(0)}to{transform:rotate(360deg)}}.support-icon-spin{animation:spin-slow 4s linear infinite}.btn-loading{opacity:.7;pointer-events:none}</style>' . "\n";
}

function user_header(): void {
    echo '<header class="bg-white sticky top-0 z-40 shadow-sm"><div class="flex items-center justify-between px-4 py-3">';
    echo '<a href="' . e(url('/users/dashboard.php')) . '" class="flex items-center gap-2"><img src="' . e(site_logo()) . '" alt="logo" class="w-8 h-8"><span class="font-bold text-lg text-blue-800">Drillifyx</span></a>';
    echo '<div class="flex items-center gap-3"><a href="' . e(url('/users/support.php')) . '" class="text-blue-600"><svg class="w-6 h-6 support-icon-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></a>';
    echo '<a href="' . e(url('/users/notifications.php')) . '" class="text-gray-600 relative"><i class="fas fa-bell text-xl"></i></a></div>';
    echo '</div></header>';
}

function user_nav(string $active): void {
    $items = [
        'dashboard' => ['/users/dashboard.php', 'Home', 'fa-home'],
        'hash' => ['/users/hash.php', 'Hash', 'fa-hashtag'],
        'spin' => ['/users/spin.php', 'Spin', 'fa-sync'],
        'withdraw' => ['/users/withdraw.php', 'Withdraw', 'fa-wallet'],
        'profile' => ['/users/profile.php', 'Profile', 'fa-user'],
    ];
    echo '<nav class="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur z-50 border-t flex">';
    foreach ($items as $key => [$href, $label, $icon]) {
        $cls = 'bottom-nav-item flex-1 flex flex-col items-center py-2 text-xs text-gray-500 relative' . ($key === $active ? ' active' : '');
        echo '<a href="' . e(url($href)) . '" class="' . $cls . '"><i class="fas ' . $icon . ' text-lg"></i>' . e($label) . '</a>';
    }
    echo '</nav>';
}

function user_footer_scripts(): void {
    echo '<script>window.APP_BASE=' . json_encode(base_path()) . ';</script>';
    echo <<<'HTML'
<script>
function openModal(id){var m=document.getElementById(id);if(m){m.classList.remove('hidden');document.body.style.overflow='hidden';}}
function closeModal(id){var m=document.getElementById(id);if(m){m.classList.add('hidden');document.body.style.overflow='';}}
function showToast(message,type){type=type||'success';var bg=type==='success'?'background:#16a34a':'background:#dc2626';var t=document.createElement('div');t.setAttribute('style','position:fixed;top:64px;left:50%;transform:translateX(-50%);'+bg+';color:#fff;padding:10px 18px;border-radius:10px;z-index:9999;font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,.2)');t.textContent=message;document.body.appendChild(t);setTimeout(function(){t.style.opacity='0';t.style.transition='opacity .3s';setTimeout(function(){t.remove();},300);},2000);}
function setLoading(btn,loading,label){if(!btn)return;if(loading){btn.dataset.orig=btn.innerHTML;btn.classList.add('btn-loading');btn.disabled=true;btn.innerHTML=(label||'Processing...');}else{btn.classList.remove('btn-loading');btn.disabled=false;if(btn.dataset.orig)btn.innerHTML=btn.dataset.orig;}}
</script>
HTML;
}
