// navigation.js
export function setupNavigation() {

    document.addEventListener('DOMContentLoaded', () => {
        const externalNav = document.getElementById('menu-media');
        const links = externalNav.querySelectorAll('a');

        function setActiveLink() {
            links.forEach(link => {
                const parentLi = link.closest('li');
                if (parentLi) {
                    parentLi.classList.remove('current');
                }
            });

            const activeLink = Array.from(links).find(link => link.href === window.location.href);
            if (activeLink) {
                const parentLi = activeLink.closest('li');
                if (parentLi) {
                    parentLi.classList.add('current');
                }
            }
        }

        // Initial setting of active link
        setActiveLink();

        // Update active link on navigation
        links.forEach(link => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                window.history.pushState(null, '', link.href);
                setActiveLink();
                // Manually trigger React Router navigation
                const navEvent = new PopStateEvent('popstate');
                dispatchEvent(navEvent);
            });
        });

        // Handle browser navigation events (back/forward)
        window.addEventListener('popstate', setActiveLink);
    });
}
