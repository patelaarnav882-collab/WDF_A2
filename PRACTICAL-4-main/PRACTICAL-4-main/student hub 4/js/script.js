/* =====================================================
   STUDENTHUB - PRACTICAL 4 JAVASCRIPT
   Single DOMContentLoaded entry point. Every feature
   guards against missing elements so one shared file
   runs safely on all pages.
   ===================================================== */

document.addEventListener("DOMContentLoaded", function () {

    "use strict";

    /* =====================================================
       1. FAQ - COLLAPSIBLE QUESTIONS
       ===================================================== */

    var faqCards = document.querySelectorAll(".faq-card");

    faqCards.forEach(function (card) {

        var question = card.querySelector("h3");
        var answer = card.querySelector("p");

        if (!question || !answer) {
            return;
        }

        // Start collapsed; `hidden` keeps answers accessible to
        // assistive tech state handling via aria-expanded.
        answer.hidden = true;

        question.setAttribute("role", "button");
        question.setAttribute("tabindex", "0");
        question.setAttribute("aria-expanded", "false");

        function toggleAnswer() {
            var isOpen =
                question.getAttribute("aria-expanded") === "true";

            question.setAttribute(
                "aria-expanded",
                String(!isOpen)
            );

            answer.hidden = isOpen;
        }

        question.addEventListener("click", toggleAnswer);

        question.addEventListener("keydown", function (event) {

            if (event.key === "Enter" || event.key === " ") {

                event.preventDefault();

                toggleAnswer();

            }

        });

    });


    /* =====================================================
       2. EVENT DETAILS MODAL
       ===================================================== */

    var eventModal =
        document.querySelector("#eventModal");

    var modalOpenButtons =
        document.querySelectorAll(".modal-open");

    var modalCloseButtons =
        document.querySelectorAll(".modal-close");

    var modalTitle =
        document.querySelector("#modalTitle");

    var modalText =
        document.querySelector("#modalText");

    var lastFocusedElement = null;

    function openModal(title, content) {

        if (!eventModal) {
            return;
        }

        lastFocusedElement = document.activeElement;

        if (modalTitle) {
            modalTitle.textContent = title || "Event Details";
        }

        if (modalText) {
            modalText.textContent = content || "";
        }

        eventModal.hidden = false;

        // Move keyboard focus inside the dialog.
        var focusTarget =
            eventModal.querySelector(".modal-close-icon") ||
            eventModal.querySelector(".modal-close") ||
            eventModal.querySelector("button");

        if (focusTarget) {
            focusTarget.focus();
        }

    }

    function closeModal() {

        if (!eventModal || eventModal.hidden) {
            return;
        }

        eventModal.hidden = true;

        // Return focus to the button that opened the modal.
        if (lastFocusedElement && lastFocusedElement.focus) {
            lastFocusedElement.focus();
        }

    }

    if (eventModal) {

        modalOpenButtons.forEach(function (button) {

            button.addEventListener("click", function () {

                openModal(
                    button.getAttribute("data-title"),
                    button.getAttribute("data-content")
                );

            });

        });


        modalCloseButtons.forEach(function (button) {

            button.addEventListener("click", closeModal);

        });


        // Clicking the backdrop (outside the dialog) closes it.
        eventModal.addEventListener("click", function (event) {

            if (event.target === eventModal) {
                closeModal();
            }

        });


        document.addEventListener("keydown", function (event) {

            if (
                event.key === "Escape" &&
                !eventModal.hidden
            ) {
                closeModal();
            }

        });

    }


    /* =====================================================
       3. HOME PAGE CONTENT SLIDER
       ===================================================== */

    var slides =
        document.querySelectorAll(".slide");

    var previousButton =
        document.querySelector(".slider-prev");

    var nextButton =
        document.querySelector(".slider-next");

    var sliderStatus =
        document.querySelector(".slider-status");

    var currentSlide = 0;

    function showSlide(index) {

        if (slides.length === 0) {
            return;
        }

        // Wrap around safely.
        if (index < 0) {
            index = slides.length - 1;
        }

        if (index >= slides.length) {
            index = 0;
        }

        currentSlide = index;

        slides.forEach(function (slide) {
            slide.classList.remove("active-slide");
        });

        slides[currentSlide].classList.add("active-slide");

        if (sliderStatus) {

            sliderStatus.textContent =
                (currentSlide + 1) + " / " + slides.length;

        }

    }

    // Initialise the counter on pages that have a slider.
    if (slides.length > 0) {
        showSlide(0);
    }

    if (nextButton && slides.length > 0) {

        nextButton.addEventListener("click", function () {
            showSlide(currentSlide + 1);
        });

    }


    if (previousButton && slides.length > 0) {

        previousButton.addEventListener("click", function () {
            showSlide(currentSlide - 1);
        });

    }


    /* =====================================================
       4. NOTIFICATION BANNER
       ===================================================== */

    var notificationBanner =
        document.querySelector("#notificationBanner");

    var notificationClose =
        document.querySelector("#notificationClose");

    if (notificationClose && notificationBanner) {

        notificationClose.addEventListener("click", function () {

            notificationBanner.style.display = "none";

        });

    }


    /* =====================================================
       5. HAMBURGER NAVIGATION
       ===================================================== */

    var menuToggle =
        document.querySelector("#menuToggle");

    var mainNav =
        document.querySelector("#mainNav");

    function setMenuOpen(isOpen) {

        if (!menuToggle || !mainNav) {
            return;
        }

        mainNav.classList.toggle("menu-open", isOpen);

        menuToggle.setAttribute(
            "aria-expanded",
            String(isOpen)
        );

        menuToggle.setAttribute(
            "aria-label",
            isOpen
                ? "Close navigation menu"
                : "Open navigation menu"
        );

    }

    if (menuToggle && mainNav) {

        menuToggle.addEventListener("click", function () {

            var isOpen =
                mainNav.classList.contains("menu-open");

            setMenuOpen(!isOpen);

        });

        // Close the mobile menu when a link is chosen.
        mainNav.addEventListener("click", function (event) {

            if (event.target.closest("a")) {
                setMenuOpen(false);
            }

        });

        // Escape closes the mobile menu.
        document.addEventListener("keydown", function (event) {

            if (
                event.key === "Escape" &&
                mainNav.classList.contains("menu-open")
            ) {
                setMenuOpen(false);
                menuToggle.focus();
            }

        });

        // Reset when resizing back to desktop layout.
        window.addEventListener("resize", function () {

            if (window.innerWidth >= 600) {
                setMenuOpen(false);
            }

        });

    }


    /* =====================================================
       6. LIGHT / DARK THEME SWITCHER (ALL PAGES)
       The tiny inline script right after <body> already
       restored the saved class before first paint; here
       we sync the button and persist future changes.
       ===================================================== */

    var themeToggle = document.querySelector("#themeToggle");
    var themeIcon = document.querySelector(".theme-icon");
    var themeLabel = document.querySelector(".theme-label");

    function readSavedTheme() {

        try {
            return localStorage.getItem("studentHubTheme");
        } catch (error) {
            return null;
        }

    }

    function updateThemeButton() {

        if (!themeToggle) {
            return;
        }

        var isDark =
            document.body.classList.contains("dark-mode");

        if (themeIcon) {
            themeIcon.textContent = isDark ? "☀️" : "🌙";
        }

        if (themeLabel) {
            themeLabel.textContent = isDark ? "Light mode" : "Dark mode";
        }

        var label = isDark
            ? "Switch to light mode"
            : "Switch to dark mode";

        themeToggle.setAttribute("aria-label", label);
        themeToggle.setAttribute("title", label);

    }

    // Re-apply the saved theme in case the early inline
    // script was blocked (e.g. storage unavailable).
    var savedTheme = readSavedTheme();

    if (savedTheme === "dark") {
        document.body.classList.add("dark-mode");
    } else if (savedTheme === "light") {
        document.body.classList.remove("dark-mode");
    }

    updateThemeButton();

    if (themeToggle) {

        themeToggle.addEventListener("click", function () {

            var isDark =
                document.body.classList.toggle("dark-mode");

            try {

                localStorage.setItem(
                    "studentHubTheme",
                    isDark ? "dark" : "light"
                );

            } catch (error) {
                /* Storage unavailable: theme still applies. */
            }

            updateThemeButton();

        });

    }

});
