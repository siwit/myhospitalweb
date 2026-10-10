// assets/js/script.js

document.addEventListener('DOMContentLoaded', () => {
    const toggleSwitch = document.querySelector('#grayscale-toggle');
    const body = document.body;

    // Check localStorage for saved mode
    if (localStorage.getItem('grayscale') === 'true') {
        body.classList.add('grayscale-mode');
        if (toggleSwitch) toggleSwitch.checked = true;
    }

    // Toggle event
    if (toggleSwitch) {
        toggleSwitch.addEventListener('change', () => {
            if (toggleSwitch.checked) {
                body.classList.add('grayscale-mode');
                localStorage.setItem('grayscale', 'true');
            } else {
                body.classList.remove('grayscale-mode');
                localStorage.setItem('grayscale', 'false');
            }
        });
    }

    // Mobile Menu Toggle
    const hamburger = document.querySelector('.hamburger');
    const navLinks = document.querySelector('.nav-links');

    if (hamburger) {
        hamburger.addEventListener('click', () => {
            navLinks.classList.toggle('active');
        });
    }

    // Close user dropdown when clicking outside
    document.addEventListener('click', function (event) {
        const userDropdown = document.getElementById('userDropdown');
        if (userDropdown && !userDropdown.contains(event.target)) {
            document.getElementById('userDropdownContent').classList.remove('show');
        }
    });
    // Popup Handling
    const popupOverlay = document.getElementById('welcomePopup');
    if (popupOverlay) {
        const hidePopup = localStorage.getItem('hide_popup');
        if (hidePopup !== 'true') {
            popupOverlay.style.display = 'flex';
        }
    }
    const bannerSlider = document.querySelector('.banner-slider');
    if (bannerSlider) {
        const slides = bannerSlider.querySelectorAll('.banner-slide');
        const dots = bannerSlider.querySelectorAll('.slider-dot');
        const prevBtn = bannerSlider.querySelector('.slider-prev');
        const nextBtn = bannerSlider.querySelector('.slider-next');
        let currentIndex = 0;

        const showSlide = (index) => {
            slides.forEach((slide, idx) => slide.classList.toggle('active', idx === index));
            dots.forEach((dot, idx) => dot.classList.toggle('active', idx === index));
            currentIndex = index;
        };

        const startAutoPlay = () => setInterval(() => showSlide((currentIndex + 1) % slides.length), 5000);
        let autoPlay = startAutoPlay();

        const resetAutoPlay = () => {
            clearInterval(autoPlay);
            autoPlay = startAutoPlay();
        };

        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                showSlide((currentIndex - 1 + slides.length) % slides.length);
                resetAutoPlay();
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                showSlide((currentIndex + 1) % slides.length);
                resetAutoPlay();
            });
        }

        dots.forEach(dot => {
            dot.addEventListener('click', () => {
                const idx = Number(dot.getAttribute('data-index'));
                showSlide(idx);
                resetAutoPlay();
            });
        });
    }
});

function closePopup() {
    const popupOverlay = document.getElementById('welcomePopup');
    const dontShowCheckbox = document.getElementById('dontShowAgain');

    if (dontShowCheckbox && dontShowCheckbox.checked) {
        localStorage.setItem('hide_popup', 'true');
    }

    if (popupOverlay) {
        popupOverlay.style.display = 'none';
    }
}

// User Menu Toggle Function
function toggleUserMenu() {
    const dropdownContent = document.getElementById('userDropdownContent');
    dropdownContent.classList.toggle('show');
}
