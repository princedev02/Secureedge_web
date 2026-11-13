document.addEventListener('DOMContentLoaded', () => {
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  if (typeof AOS !== 'undefined') {
    AOS.init({
      duration: 1400,
      easing: 'ease-in-out',
      once: true,
      disable: () => reducedMotion.matches,
    });
    document.fonts.ready.then(() => AOS.refresh());
    window.addEventListener('load', () => AOS.refresh(), { once: true });
  }

  const illustration = document.querySelector('.dpdp-control-art');
  if (illustration && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver(entries => {
      for (const entry of entries) {
        entry.target.classList.toggle('is-visible', entry.isIntersecting);
      }
    }, { threshold: 0.15 });
    observer.observe(illustration);
  } else if (illustration) {
    illustration.classList.add('is-visible');
  }
});
