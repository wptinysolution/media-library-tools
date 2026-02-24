export function setupNavigation(): void {
    document.addEventListener('DOMContentLoaded', () => {
        const externalNav = document.getElementById('menu-media');
        if (!externalNav) return;

        const links = externalNav.querySelectorAll<HTMLAnchorElement>('a');

        function setActiveLink(): void {
            links.forEach(link => {
                const parentLi = link.closest('li');
                if (parentLi) {
                    parentLi.classList.remove('current');
                }
            });

            const activeLink = Array.from(links).find(link => link.href === window.location.href);
            if (activeLink) {
                const parentLi = activeLink.closest('li');
                if (parentLi && parentLi.querySelector('.tsmlt-is-submenu')) {
                    parentLi.classList.add('current');
                }
            }
        }

        setActiveLink();

        links.forEach(link => {
            link.addEventListener('click', (event) => {
                const parentLi = link.closest('li');
                if (parentLi && parentLi.querySelector('.tsmlt-is-submenu')) {
                    event.preventDefault();
                    window.history.pushState(null, '', link.href);
                    setActiveLink();
                    const navEvent = new PopStateEvent('popstate');
                    dispatchEvent(navEvent);
                }
            });
        });

        window.addEventListener('popstate', setActiveLink);
    });
}
