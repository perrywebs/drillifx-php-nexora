<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
$u = require_login();
$uid = (int)$u['id'];
$pdo = db();
// AJAX delete (existing frontend fetch posts here)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    if (!csrf_check($_POST['csrf'] ?? null)) { echo json_encode(['success'=>false,'message'=>'Invalid session.']); exit; }
    if (isset($_POST['delete_notification'], $_POST['notification_id'])) {
        $st = $pdo->prepare('DELETE FROM notifications WHERE id=? AND user_id=?');
        $st->execute([(int)$_POST['notification_id'], $uid]);
        echo json_encode(['success'=>true]); exit;
    }
    if (isset($_POST['delete_activity'], $_POST['activity_id'])) {
        $st = $pdo->prepare('DELETE FROM activity_log WHERE id=? AND user_id=?');
        $st->execute([(int)$_POST['activity_id'], $uid]);
        echo json_encode(['success'=>true]); exit;
    }
    echo json_encode(['success'=>false,'message'=>'Invalid request.']); exit;
}
$tab = ($_GET['tab'] ?? 'notifications') === 'activity' ? 'activity' : 'notifications';
$st = $pdo->prepare('SELECT COUNT(*) c FROM notifications WHERE user_id=?'); $st->execute([$uid]); $notifCount = (int)$st->fetch()['c'];
$st = $pdo->prepare('SELECT COUNT(*) c FROM activity_log WHERE user_id=?'); $st->execute([$uid]); $actCount = (int)$st->fetch()['c'];
$st = $pdo->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 50'); $st->execute([$uid]); $notifs = $st->fetchAll();
// mark notifications read on view
$pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$uid]);
$st = $pdo->prepare('SELECT * FROM activity_log WHERE user_id=? ORDER BY id DESC LIMIT 50'); $st->execute([$uid]); $activities = $st->fetchAll();
?><!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
<?php if (site_favicon()): ?><link rel="icon" href="<?php echo e(site_favicon()); ?>"><?php endif; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Notifications - <?php echo e(site_name()); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Signika+Negative:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Signika Negative', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Signika Negative', sans-serif;
            padding-bottom: 70px;
            background: linear-gradient(180deg, #f8fafc 0%, #eff6ff 100%);
            min-height: 100vh;
        }

        /* Removed gradient-header, now using clean white header */
        .bottom-nav-item.active {
            color: #2563eb;
            background: linear-gradient(180deg, rgba(37, 99, 235, 0.1) 0%, transparent 100%);
        }

        .bottom-nav-item.active::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 3px;
            background: #2563eb;
            border-radius: 0 0 4px 4px;
        }

        /* Badge animation */
        @keyframes pulse-badge {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }
        }

        .badge-pulse {
            animation: pulse-badge 2s ease-in-out infinite;
        }

        /* Support icon spinning animation */
        @keyframes spin-slow {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .support-icon-spin {
            animation: spin-slow 4s linear infinite;
        }
    </style>
</head>

<body class="min-h-screen">
    <!-- Redesigned header - clean white with subtle shadow -->
    <header class="bg-white border-b border-gray-100 shadow-sm sticky top-0 z-40">
        <div class="px-4 py-3">
            <div class="flex items-center justify-between">
                <a href="/users/dashboard.php" class="flex items-center gap-2">
                    <img src="<?php echo e(site_logo()); ?>" alt="USDC Core" class="w-8 h-8">
                    <span class="text-lg font-bold text-gray-800"><?php echo e(site_name()); ?></span>
                </a>
                <div class="flex items-center gap-2">
                    <!-- Added support icon -->
                    <!-- Added animation class to support icon -->
                    <a href="/users/support.php"
                        class="w-9 h-9 bg-gray-50 hover:bg-blue-50 rounded-xl flex items-center justify-center transition group">
                        <svg class="w-5 h-5 text-gray-500 group-hover:text-blue-600 support-icon-spin" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </a>
                    <!-- Notifications with unread badge -->
                    <a href="/users/notifications.php"
                        class="w-9 h-9 bg-gray-50 hover:bg-blue-50 rounded-xl flex items-center justify-center transition relative group">
                        <svg class="w-5 h-5 text-gray-500 group-hover:text-blue-600" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </header>


    <main class="px-4 py-4">

        <!-- Add CSS for swipe functionality -->
        <style>
            .swipe-container {
                overflow: hidden;
                position: relative;
            }

            .swipe-item {
                position: relative;
                transition: transform 0.3s ease;
                touch-action: pan-y;
            }

            .swipe-item.swiping {
                transition: none;
            }

            .delete-bg {
                position: absolute;
                right: 0;
                top: 0;
                bottom: 0;
                width: 80px;
                background: linear-gradient(to right, #ef4444, #dc2626);
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 0.75rem;
            }

            .delete-bg svg {
                color: white;
                width: 24px;
                height: 24px;
            }
        </style>

        <!-- Header Card -->
        <div
            class="bg-gradient-to-br from-blue-600 via-blue-700 to-blue-900 rounded-2xl p-4 mb-4 text-white relative overflow-hidden">
            <div class="absolute top-0 right-0 w-24 h-24 bg-white/10 rounded-full -mr-8 -mt-8"></div>
            <div class="absolute bottom-0 left-0 w-16 h-16 bg-white/5 rounded-full -ml-6 -mb-6"></div>

            <div class="relative">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-xl font-bold">Notifications</h1>
                        <p class="text-blue-200 text-xs">Stay updated with latest alerts</p>
                    </div>
                    <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 mt-4">
                    <div class="bg-white/10 backdrop-blur rounded-xl p-3 text-center">
                        <p class="text-2xl font-bold"><?php echo (int)$notifCount; ?></p>
                        <p class="text-blue-200 text-xs">Total Messages</p>
                    </div>
                    <div class="bg-white/10 backdrop-blur rounded-xl p-3 text-center">
                        <p class="text-2xl font-bold"><?php echo (int)$actCount; ?></p>
                        <p class="text-blue-200 text-xs">Activities</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add tabs for Notifications and Activity -->
        <div class="flex gap-2 mb-4">
            <a href="/users/notifications.php?tab=notifications"
                class="flex-1 py-2.5 px-4 rounded-xl text-center text-sm font-semibold transition <?php echo $tab === 'activity' ? 'bg-white text-gray-600 hover:bg-gray-50' : 'bg-blue-600 text-white'; ?>">
                Notifications
            </a>
            <a href="/users/notifications.php?tab=activity"
                class="flex-1 py-2.5 px-4 rounded-xl text-center text-sm font-semibold transition <?php echo $tab === 'activity' ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'; ?>">
                Activity Log
            </a>
        </div>

        <!-- Notifications Tab -->
        <div class="mb-4">
            <div class="flex items-center justify-between mb-2">
                <h2 class="text-gray-700 font-semibold text-sm"><?php echo $tab === "activity" ? "Activity Log" : "All Notifications"; ?></h2>
                <span class="text-gray-400 text-xs">Swipe left to delete</span>
            </div>

            <?php if ($tab === 'activity'): ?>
            <?php if ($activities): foreach ($activities as $a): ?>
            <div class="swipe-container mb-2"><div class="delete-bg"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg></div>
            <div class="swipe-item bg-white rounded-xl shadow-sm p-3 flex items-center gap-3" data-id="<?php echo (int)$a['id']; ?>" data-type="activity">
                <div class="flex-1 min-w-0"><p class="text-sm text-gray-800"><?php echo e($a['message']); ?></p><p class="text-xs text-gray-400"><?php echo e(date('M j, g:i A', strtotime($a['created_at']))); ?></p></div>
            </div></div>
            <?php endforeach; else: ?>
            <div class="bg-white rounded-xl shadow-sm p-8 text-center"><p class="text-gray-500 font-medium">No activity yet</p><p class="text-gray-400 text-sm mt-1">Your actions will appear here</p></div>
            <?php endif; ?>
            <?php else: ?>
            <?php if ($notifs): foreach ($notifs as $n): ?>
            <div class="swipe-container mb-2"><div class="delete-bg"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg></div>
            <div class="swipe-item bg-white rounded-xl shadow-sm p-3 flex items-center gap-3 <?php echo $n['is_read'] ? '' : 'border-l-4 border-blue-500'; ?>" data-id="<?php echo (int)$n['id']; ?>" data-type="notification">
                <div class="flex-1 min-w-0"><p class="font-semibold text-sm text-gray-800"><?php echo e($n['title']); ?></p><p class="text-sm text-gray-600"><?php echo e($n['message']); ?></p><p class="text-xs text-gray-400"><?php echo e(date('M j, g:i A', strtotime($n['created_at']))); ?></p></div>
                <?php if (!$n['is_read']): ?><span class="w-2 h-2 bg-blue-600 rounded-full flex-shrink-0"></span><?php endif; ?>
            </div></div>
            <?php endforeach; else: ?>
            <div class="bg-white rounded-xl shadow-sm p-8 text-center">
                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                </div>
                <p class="text-gray-500 font-medium">No notifications yet</p>
                <p class="text-gray-400 text-sm mt-1">We'll notify you when something important happens</p>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>


        <div class="mb-4"></div>

        <script>
            // Swipe to delete functionality
            document.querySelectorAll('.swipe-item').forEach(item => {
                let startX, currentX, isDragging = false;
                const threshold = 80;

                const onStart = (e) => {
                    startX = e.type === 'touchstart' ? e.touches[0].clientX : e.clientX;
                    isDragging = true;
                    item.classList.add('swiping');
                };

                const onMove = (e) => {
                    if (!isDragging) return;
                    currentX = e.type === 'touchmove' ? e.touches[0].clientX : e.clientX;
                    const diff = Math.min(0, currentX - startX);
                    if (diff < 0) {
                        item.style.transform = `translateX(${Math.max(diff, -threshold)}px)`;
                    }
                };

                const onEnd = () => {
                    if (!isDragging) return;
                    isDragging = false;
                    item.classList.remove('swiping');

                    const diff = currentX - startX;
                    if (diff < -threshold * 0.6) {
                        item.style.transform = `translateX(-${threshold}px)`;

                        // Delete after animation
                        setTimeout(() => {
                            const id = item.dataset.id;
                            const type = item.dataset.type;
                            const formData = new FormData();
                             formData.append('csrf', '<?php echo csrf_token(); ?>');

                            if (type === 'notification') {
                                formData.append('delete_notification', '1');
                                formData.append('notification_id', id);
                            } else {
                                formData.append('delete_activity', '1');
                                formData.append('activity_id', id);
                            }

                            fetch((window.APP_BASE||'')+'/users/notifications.php', {
                                method: 'POST',
                                body: formData,
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            }).then(response => response.json()).then(data => {
                                if (data.success) {
                                    item.parentElement.style.height = item.parentElement.offsetHeight + 'px';
                                    item.parentElement.style.overflow = 'hidden';
                                    item.parentElement.style.transition = 'height 0.3s ease, opacity 0.3s ease';
                                    requestAnimationFrame(() => {
                                        item.parentElement.style.height = '0px';
                                        item.parentElement.style.opacity = '0';
                                        item.parentElement.style.marginBottom = '0';
                                    });
                                    setTimeout(() => item.parentElement.remove(), 300);
                                }
                            });
                        }, 200);
                    } else {
                        item.style.transform = 'translateX(0)';
                    }
                };

                item.addEventListener('touchstart', onStart, { passive: true });
                item.addEventListener('touchmove', onMove, { passive: true });
                item.addEventListener('touchend', onEnd);
                item.addEventListener('mousedown', onStart);
                item.addEventListener('mousemove', onMove);
                item.addEventListener('mouseup', onEnd);
                item.addEventListener('mouseleave', () => {
                    if (isDragging) {
                        isDragging = false;
                        item.classList.remove('swiping');
                        item.style.transform = 'translateX(0)';
                    }
                });
            });
        </script>

    </main>

    <!-- Redesigned bottom navigation with modern SVG icons -->
    <nav
        class="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-lg border-t border-gray-100 shadow-2xl z-50 safe-area-bottom">
        <div class="flex justify-around items-center h-16 max-w-lg mx-auto">
            <!-- Home -->
            <!-- Added pathPrefix to all navigation links -->
            <a href="/users/dashboard.php"
                class="bottom-nav-item flex flex-col items-center justify-center flex-1 h-full relative text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span class="text-xs font-medium">Home</span>
            </a>
            <!-- Hash -->
            <a href="/users/hash.php"
                class="bottom-nav-item flex flex-col items-center justify-center flex-1 h-full relative text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                </svg>
                <span class="text-xs font-medium">Hash</span>
            </a>
            <!-- Spin -->
            <a href="/users/spin.php"
                class="bottom-nav-item flex flex-col items-center justify-center flex-1 h-full relative text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span class="text-xs font-medium">Spin</span>
            </a>
            <!-- Withdraw -->
            <a href="/users/withdraw.php"
                class="bottom-nav-item flex flex-col items-center justify-center flex-1 h-full relative text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <span class="text-xs font-medium">Withdraw</span>
            </a>
            <!-- Profile -->
            <a href="/users/profile.php"
                class="bottom-nav-item flex flex-col items-center justify-center flex-1 h-full relative text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span class="text-xs font-medium">Profile</span>
            </a>
        </div>
    </nav>

    <script>
        // Modal functionality
        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
            document.body.style.overflow = '';
        }

        // Toast notification
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `fixed top-16 left-1/2 -translate-x-1/2 px-4 py-2 rounded-xl text-sm z-50 shadow-lg ${type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.3s';
                setTimeout(() => toast.remove(), 300);
            }, 2000);
        }
    </script>
</body>

</html>
<?php echo render_toast_queue(); ?>
