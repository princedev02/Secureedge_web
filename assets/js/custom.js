// -------------------- GLOBAL OPTIMIZATION --------------------

document.addEventListener('DOMContentLoaded', () => {

    // 1. NAVBAR SCROLL (Optimized with Passive Listener)
    const navbar = document.querySelector('.navbar');
    if (navbar) {
    
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
                navbar.classList.remove('transparent');
            } else {
                navbar.classList.remove('scrolled');
                navbar.classList.add('transparent');
            }
        }, { passive: true });
    }

    // 2. DRAG-TIPS TOGGLE
    document.querySelectorAll('.drag-tips').forEach(el => {
        el.addEventListener('click', function() {
            const para = this.parentElement.querySelector('.hidePara');
            const dragText = this.querySelector('.dragText');
            if (!para || !dragText) return;

            if (para.classList.contains('show')) {
                para.classList.remove('show');
               
                setTimeout(() => para.style.display = 'none', 400);
                dragText.classList.remove('moved');
            } else {
                para.style.display = 'block';
                setTimeout(() => para.classList.add('show'), 10);
                dragText.classList.add('moved');
            }
        });
    });

    // 3. SERVICE CARD HOVER (Caching Selectors)
    const numCards = document.querySelectorAll('.numCard-wrapper');
    document.querySelectorAll('.service-card-wrapper').forEach(card => {
        card.addEventListener('mouseenter', () => {
            numCards.forEach(n => n.style.opacity = '0');
        });
        card.addEventListener('mouseleave', () => {
            numCards.forEach(n => n.style.opacity = '1');
        });
    });

    // 4. LOGO SLIDER 
    const logoSlider = document.querySelector('.logo-slider');
    if (logoSlider && typeof $ !== 'undefined' && $.isFunction($.fn.slick)) {
        $(logoSlider).slick({
            infinite: true,
            slidesToShow: 2,
            slidesToScroll: 1,
            speed: 1600,
            autoplay: true,
            autoplaySpeed: 0,
            cssEase: "linear",
            swipeToSlide: true,
            arrows: false,
            pauseOnHover: true,
            variableWidth: true,
            
            adaptiveHeight: false, 
            centerMode: true,
            centerPadding: "0px",
            responsive: [
                { breakpoint: 992, settings: { slidesToShow: 2 } },
                { breakpoint: 768, settings: { slidesToShow: 2 } },
                { breakpoint: 576, settings: { slidesToShow: 2 } }
            ]
        });
    }

    // number counting js
    const counters = document.querySelectorAll('.numData');
    if (counters.length) {
        const runCounter = counter => {
            const target = parseFloat(counter.getAttribute("data-target")) || 0;
            const suffix = counter.getAttribute("data-suffix") || "";
            const duration = 1500;
            let startTimestamp = null;

            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                const currentVal = Math.floor(progress * target);

                counter.innerText = currentVal.toLocaleString() + suffix;

                if (progress < 1) {
                    window.requestAnimationFrame(step);
                } else {
                    counter.innerText = target.toLocaleString() + suffix;
                }
            };
            window.requestAnimationFrame(step);
        };

        const observer = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    runCounter(entry.target);
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        counters.forEach(counter => observer.observe(counter));
    }

    // 6. AOS INIT (Functional check)
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 1400,
            easing: "ease-in-out",
            once: true,
        });
    }

    // 7. SCROLL TO SECTION
    const getStartedBtn = document.querySelector('.btn-getstart-eadge');
    const targetSection = document.querySelector('#next-section');
    if (getStartedBtn && targetSection) {
        getStartedBtn.addEventListener('click', e => {
            e.preventDefault();
            targetSection.scrollIntoView({ behavior: 'smooth' });
        });
    }

    // 8. VIDEO POPUP
    const videoTrigger = document.querySelector('.wrapper-imagemainrotation a');
    const popup = document.getElementById('videoPopup');
    const video = document.getElementById('localVideo');
    const closeBtn = document.querySelector('.close-btn');

    if (videoTrigger && popup && video && closeBtn) {
        const closePopup = () => {
            popup.style.display = 'none';
            document.body.classList.remove('popup-active');
            video.pause();
        };

        videoTrigger.addEventListener('click', e => {
            e.preventDefault();
            popup.style.display = 'flex';
            document.body.classList.add('popup-active');
            video.currentTime = 0;
            video.play();
        });

        closeBtn.addEventListener('click', closePopup);
        popup.addEventListener('click', e => { if (e.target === popup) closePopup(); });
    }
});

// 9. VIDEO LAZY LOAD (Non-blocking)

window.addEventListener('load', () => {
    const videoEl = document.getElementById('localVideo');
    if (videoEl) videoEl.load();
});