(function () {
    'use strict';

    const isLocalhost = ['localhost', '127.0.0.1'].includes(window.location.hostname);
    const isSecure = window.location.protocol === 'https:' || isLocalhost;

    if ('serviceWorker' in navigator && isSecure) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/service-worker.js')
                .then(function () {
                    document.documentElement.classList.add('pwa-ready');
                })
                .catch(function () {
                    document.documentElement.classList.add('pwa-register-failed');
                });
        });
    }

    let deferredPrompt = null;

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredPrompt = event;
        showInstallBanner();
    });

    window.addEventListener('appinstalled', function () {
        deferredPrompt = null;
        localStorage.setItem('greennet_pwa_installed', '1');
        hideInstallBanner();
    });

    function shouldShowBanner() {
        if (localStorage.getItem('greennet_pwa_hide') === '1') {
            return false;
        }

        if (localStorage.getItem('greennet_pwa_installed') === '1') {
            return false;
        }

        const path = window.location.pathname;

        return (
            path === '/dashboard'
            || path.startsWith('/my/')
            || path === '/support'
            || path === '/announcements'
            || path === '/login'
        );
    }

    function showInstallBanner() {
        if (!deferredPrompt || !shouldShowBanner()) {
            return;
        }

        if (document.getElementById('greennet-install-banner')) {
            return;
        }

        const banner = document.createElement('div');
        banner.id = 'greennet-install-banner';
        banner.setAttribute('dir', 'rtl');
        banner.innerHTML = `
            <div class="greennet-install-card">
                <div class="greennet-install-icon">G</div>
                <div class="greennet-install-text">
                    <strong>ثبّت تطبيق GreenNet</strong>
                    <span>أضفه على شاشة الموبايل للوصول السريع.</span>
                </div>
                <button type="button" class="greennet-install-btn" id="greennet-install-action">تثبيت</button>
                <button type="button" class="greennet-install-close" id="greennet-install-close">×</button>
            </div>
        `;

        const style = document.createElement('style');
        style.id = 'greennet-install-style';
        style.textContent = `
            #greennet-install-banner {
                position: fixed;
                left: 50%;
                bottom: 84px;
                transform: translateX(-50%);
                width: min(520px, calc(100% - 22px));
                z-index: 1000;
                font-family: Arial, sans-serif;
            }

            .greennet-install-card {
                display: grid;
                grid-template-columns: 42px 1fr auto 34px;
                align-items: center;
                gap: 10px;
                background: rgba(255,255,255,0.98);
                border: 1px solid #e5e7eb;
                border-radius: 20px;
                padding: 10px;
                box-shadow: 0 18px 60px rgba(15, 23, 42, 0.18);
                backdrop-filter: blur(12px);
            }

            .greennet-install-icon {
                width: 42px;
                height: 42px;
                border-radius: 14px;
                background: var(--greennet-theme, #16a34a);
                color: #ffffff;
                display: grid;
                place-items: center;
                font-weight: 900;
                font-size: 20px;
            }

            .greennet-install-text {
                display: grid;
                gap: 3px;
                min-width: 0;
            }

            .greennet-install-text strong {
                font-size: 13px;
                color: #111827;
            }

            .greennet-install-text span {
                font-size: 12px;
                color: #64748b;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .greennet-install-btn {
                border: 0;
                background: var(--greennet-theme, #16a34a);
                color: #ffffff;
                border-radius: 13px;
                padding: 9px 12px;
                font-weight: 800;
                cursor: pointer;
                font-size: 12px;
            }

            .greennet-install-close {
                width: 32px;
                height: 32px;
                border: 0;
                border-radius: 12px;
                background: #f1f5f9;
                color: #334155;
                font-size: 20px;
                line-height: 1;
                cursor: pointer;
            }

            @media (max-width: 420px) {
                .greennet-install-card {
                    grid-template-columns: 38px 1fr 34px;
                }

                .greennet-install-btn {
                    grid-column: 1 / -1;
                    width: 100%;
                }
            }
        `;

        document.head.appendChild(style);
        document.body.appendChild(banner);

        const installButton = document.getElementById('greennet-install-action');
        const closeButton = document.getElementById('greennet-install-close');

        if (installButton) {
            installButton.addEventListener('click', async function () {
                if (!deferredPrompt) {
                    return;
                }

                deferredPrompt.prompt();

                try {
                    await deferredPrompt.userChoice;
                } catch (error) {
                    // ignore
                }

                deferredPrompt = null;
                hideInstallBanner();
            });
        }

        if (closeButton) {
            closeButton.addEventListener('click', function () {
                localStorage.setItem('greennet_pwa_hide', '1');
                hideInstallBanner();
            });
        }
    }

    function hideInstallBanner() {
        const banner = document.getElementById('greennet-install-banner');

        if (banner) {
            banner.remove();
        }
    }
})();