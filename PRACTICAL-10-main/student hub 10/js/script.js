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

    function bindFaqCard(card) {

        var question = card.querySelector("h3");
        var answer = card.querySelector("p");

        if (!question || !answer) {
            return;
        }

        // Skip cards that were already enhanced (for example
        // cards rendered later by Practical 6 Fetch code).
        if (question.hasAttribute("data-faq-bound")) {
            return;
        }
        question.setAttribute("data-faq-bound", "true");

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

    }

    function bindFaqCards(root) {
        var scope = root || document;
        scope.querySelectorAll(".faq-card").forEach(bindFaqCard);
    }

    bindFaqCards(document);

    // Shared namespace so Practical 6 code can enhance
    // dynamically rendered cards with the same behaviour.
    window.StudentHub = window.StudentHub || {};
    window.StudentHub.bindFaqCards = bindFaqCards;


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

    // Shared namespace so Practical 6 code can open the
    // same modal for dynamically rendered event cards.
    window.StudentHub = window.StudentHub || {};
    window.StudentHub.openEventModal = openModal;
    window.StudentHub.closeEventModal = closeModal;


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

    /* =====================================================
       PRACTICAL 10 - LOGIN / LOGOUT MESSAGES
       Shows server-side results (?error= / ?message=)
       without any extra page or inline script.
       ===================================================== */

    var loginStatus = document.querySelector("#loginStatus");

    if (loginStatus) {

        var loginMessages = {
            invalid: "Incorrect email or password. Please try again.",
            expired: "Your session expired after 15 minutes of inactivity. Please log in again.",
            login: "Please log in to open your dashboard.",
            unavailable: "The login service is unavailable right now. Please try again later."
        };

        try {

            var loginParams = new URLSearchParams(window.location.search);
            var loginError = loginParams.get("error");

            if (loginError && loginMessages[loginError]) {
                loginStatus.textContent = loginMessages[loginError];
                loginStatus.hidden = false;
            }

        } catch (error) {
            /* Older browsers: the form still works. */
        }

    }

    var loginNotice = document.querySelector("#loginNotice");

    if (loginNotice) {

        try {

            var noticeParams = new URLSearchParams(window.location.search);

            if (noticeParams.get("message") === "loggedout") {
                loginNotice.textContent = "You have been logged out safely.";
                loginNotice.hidden = false;
            }

        } catch (error) {
            /* Older browsers: nothing to show. */
        }

    }

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


    /* =====================================================
       7. PRACTICAL 5 - REGISTRATION FORM VALIDATION
       Runs only on pages that contain #registerForm.
       ===================================================== */

    var registerForm =
        document.querySelector("#registerForm");

    if (registerForm) {

        // Regular expressions used for validation.
        var NAME_PATTERN =
            /^[A-Za-z][A-Za-z\s.'-]{1,48}[A-Za-z.]$/;

        var EMAIL_PATTERN =
            /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

        var MOBILE_PATTERN =
            /^(\+91|91)?[6-9]\d{9}$/;

        var PASSWORD_LETTER_PATTERN = /[A-Za-z]/;
        var PASSWORD_NUMBER_PATTERN = /[0-9]/;


        function getField(id) {
            return document.getElementById(id);
        }

        function getErrorElement(id) {
            return document.getElementById(id + "-error");
        }

        function showFieldError(id, message) {

            var field = getField(id);
            var error = getErrorElement(id);

            if (error) {
                error.textContent = message;
                error.hidden = false;
            }

            if (field) {
                field.setAttribute("aria-invalid", "true");

                var group = field.closest(".form-group");
                if (group) {
                    group.classList.add("invalid");
                }
            } else if (id === "gender") {
                // Gender uses a fieldset, not a single input.
                var genderGroup =
                    registerForm.querySelector(".gender-group");
                if (genderGroup) {
                    genderGroup.classList.add("invalid");
                }
            } else if (id === "terms") {
                var termsBox =
                    registerForm.querySelector(".register-checkbox");
                if (termsBox) {
                    termsBox.classList.add("invalid");
                }
            }

        }

        function clearFieldError(id) {

            var field = getField(id);
            var error = getErrorElement(id);

            if (error) {
                error.textContent = "";
                error.hidden = true;
            }

            if (field) {
                field.removeAttribute("aria-invalid");

                var group = field.closest(".form-group");
                if (group) {
                    group.classList.remove("invalid");
                }
            }

            if (id === "gender") {
                var genderGroup =
                    registerForm.querySelector(".gender-group");
                if (genderGroup) {
                    genderGroup.classList.remove("invalid");
                }
            }

            if (id === "terms") {
                var termsBox =
                    registerForm.querySelector(".register-checkbox");
                if (termsBox) {
                    termsBox.classList.remove("invalid");
                }
            }

        }

        function readValue(id) {

            var field = getField(id);

            if (!field) {
                return "";
            }

            return field.value.trim();

        }

        function isGenderSelected() {

            var checked = registerForm.querySelector(
                'input[name="gender"]:checked'
            );

            return checked !== null;

        }

        /* ----- individual field validators ----- */

        function validateName() {

            var value = readValue("full-name");

            if (value === "") {
                showFieldError("full-name", "Please enter your full name.");
                return false;
            }

            if (value.length < 3) {
                showFieldError(
                    "full-name",
                    "Name must be at least 3 characters long."
                );
                return false;
            }

            if (!NAME_PATTERN.test(value)) {
                showFieldError(
                    "full-name",
                    "Name may contain only letters, spaces, dots, hyphens and apostrophes."
                );
                return false;
            }

            clearFieldError("full-name");
            return true;

        }

        function validateStudentId() {

            var value = readValue("student-id");

            if (value === "") {
                showFieldError("student-id", "Please enter your student ID.");
                return false;
            }

            clearFieldError("student-id");
            return true;

        }

        function validateEmail() {

            var value = readValue("email");

            if (value === "") {
                showFieldError("email", "Please enter your email address.");
                return false;
            }

            if (!EMAIL_PATTERN.test(value)) {
                showFieldError(
                    "email",
                    "Please enter a valid email address (example: name@college.edu)."
                );
                return false;
            }

            clearFieldError("email");
            return true;

        }

        function validateMobile() {

            var raw = readValue("phone");
            var digitsOnly = raw.replace(/[\s-]/g, "");

            if (raw === "") {
                showFieldError("phone", "Please enter your mobile number.");
                return false;
            }

            if (!MOBILE_PATTERN.test(digitsOnly)) {
                showFieldError(
                    "phone",
                    "Please enter a valid 10-digit mobile number starting with 6-9."
                );
                return false;
            }

            clearFieldError("phone");
            return true;

        }

        function validateCourse() {

            if (readValue("course") === "") {
                showFieldError("course", "Please select your course / program.");
                return false;
            }

            clearFieldError("course");
            return true;

        }

        function validateYear() {

            if (readValue("year") === "") {
                showFieldError("year", "Please select your year of study.");
                return false;
            }

            clearFieldError("year");
            return true;

        }

        function validateSemester() {

            if (readValue("semester") === "") {
                showFieldError("semester", "Please select your semester.");
                return false;
            }

            clearFieldError("semester");
            return true;

        }

        function validateGender() {

            if (!isGenderSelected()) {
                showFieldError("gender", "Please select your gender.");
                return false;
            }

            clearFieldError("gender");
            return true;

        }

        /* Password strength: 0 (empty) to 3 (strong). */

        function getPasswordStrength(password) {

            if (password.length === 0) {
                return 0;
            }

            var score = 0;

            if (password.length >= 8) {
                score++;
            }

            if (
                PASSWORD_LETTER_PATTERN.test(password) &&
                PASSWORD_NUMBER_PATTERN.test(password)
            ) {
                score++;
            }

            if (
                password.length >= 12 ||
                /[^A-Za-z0-9]/.test(password) ||
                (/[a-z]/.test(password) && /[A-Z]/.test(password))
            ) {
                score++;
            }

            return score;

        }

        function updatePasswordStrength() {

            var bar = getField("passwordStrengthBar");
            var text = getField("passwordStrengthText");
            var passwordField = getField("password");

            if (!bar || !text || !passwordField) {
                return;
            }

            var password = passwordField.value;
            var strength = getPasswordStrength(password);

            bar.classList.remove(
                "strength-weak",
                "strength-medium",
                "strength-strong"
            );

            text.classList.remove(
                "strength-weak",
                "strength-medium",
                "strength-strong"
            );

            if (password.length === 0) {
                text.textContent = "";
                return;
            }

            if (strength <= 1) {
                bar.classList.add("strength-weak");
                text.classList.add("strength-weak");
                text.textContent = "Password strength: Weak";
            } else if (strength === 2) {
                bar.classList.add("strength-medium");
                text.classList.add("strength-medium");
                text.textContent = "Password strength: Medium";
            } else {
                bar.classList.add("strength-strong");
                text.classList.add("strength-strong");
                text.textContent = "Password strength: Strong";
            }

        }

        function validatePassword() {

            var passwordField = getField("password");
            var password = passwordField ? passwordField.value : "";

            if (password === "") {
                showFieldError("password", "Please create a password.");
                return false;
            }

            if (password.length < 8) {
                showFieldError(
                    "password",
                    "Password must be at least 8 characters long."
                );
                return false;
            }

            if (
                !PASSWORD_LETTER_PATTERN.test(password) ||
                !PASSWORD_NUMBER_PATTERN.test(password)
            ) {
                showFieldError(
                    "password",
                    "Password must contain both letters and numbers."
                );
                return false;
            }

            clearFieldError("password");
            return true;

        }

        function validateConfirmPassword() {

            var passwordField = getField("password");
            var confirmField = getField("confirm-password");

            var password = passwordField ? passwordField.value : "";
            var confirm = confirmField ? confirmField.value : "";

            if (confirm === "") {
                showFieldError(
                    "confirm-password",
                    "Please confirm your password."
                );
                return false;
            }

            if (confirm !== password) {
                showFieldError(
                    "confirm-password",
                    "Passwords do not match. Please try again."
                );
                return false;
            }

            clearFieldError("confirm-password");
            return true;

        }

        function validateTerms() {

            var terms = getField("terms");

            if (!terms || !terms.checked) {
                showFieldError(
                    "terms",
                    "Please accept the terms and conditions to continue."
                );
                return false;
            }

            clearFieldError("terms");
            return true;

        }

        function validateWholeForm() {

            var results = [
                validateName(),
                validateStudentId(),
                validateEmail(),
                validateMobile(),
                validateCourse(),
                validateYear(),
                validateSemester(),
                validateGender(),
                validatePassword(),
                validateConfirmPassword(),
                validateTerms()
            ];

            return results.indexOf(false) === -1;

        }

        /* ----- real-time (intermediate extension) ----- */

        var liveValidators = {
            "full-name": validateName,
            "student-id": validateStudentId,
            "email": validateEmail,
            "phone": validateMobile,
            "course": validateCourse,
            "year": validateYear,
            "semester": validateSemester,
            "password": function () {
                updatePasswordStrength();
                validatePassword();
                // Keep the match message fresh while typing.
                var confirm = getField("confirm-password");
                if (confirm && confirm.value !== "") {
                    validateConfirmPassword();
                }
            },
            "confirm-password": validateConfirmPassword,
            "terms": validateTerms
        };

        Object.keys(liveValidators).forEach(function (id) {

            var field = getField(id);

            if (!field) {
                return;
            }

            var eventName =
                field.tagName === "SELECT" ? "change" : "input";

            field.addEventListener(eventName, function () {
                liveValidators[id]();
            });

            if (eventName === "input") {
                field.addEventListener("blur", function () {
                    liveValidators[id]();
                });
            }

        });

        var genderRadios = registerForm.querySelectorAll(
            'input[name="gender"]'
        );

        genderRadios.forEach(function (radio) {
            radio.addEventListener("change", validateGender);
        });

        /* ----- submit ----- */

        var successBox = getField("registerSuccess");

        function hideSuccess() {
            if (successBox) {
                successBox.textContent = "";
                successBox.hidden = true;
            }
        }

        registerForm.addEventListener("input", hideSuccess);
        registerForm.addEventListener("change", hideSuccess);

        // Practical 7: after the PHP handler saves the record it
        // redirects back here with ?registered=1 (POST/Redirect/GET).
        // Show the server-side success message in the same box.
        try {

            var params = new URLSearchParams(window.location.search);

            if (params.get("registered") === "1" && successBox) {

                successBox.textContent =
                    "Registration saved successfully! " +
                    "Thank you for joining StudentHub. " +
                    "You can now log in with your student account.";

                successBox.hidden = false;

            }

        } catch (error) {
            /* Older browsers: the server result page still works. */
        }

        registerForm.addEventListener("submit", function (event) {

            var isValid = validateWholeForm();

            if (!isValid) {

                // Invalid: stay on the page and show inline errors.
                event.preventDefault();

                hideSuccess();

                // Move focus to the first problem field.
                var firstInvalid = registerForm.querySelector(
                    '[aria-invalid="true"]'
                );

                if (firstInvalid && firstInvalid.focus) {
                    firstInvalid.focus();
                } else {
                    var genderError = getErrorElement("gender");
                    if (
                        genderError &&
                        !genderError.hidden
                    ) {
                        var firstRadio = registerForm.querySelector(
                            'input[name="gender"]'
                        );
                        if (firstRadio) {
                            firstRadio.focus();
                        }
                    }
                }

                return;

            }

            if (successBox) {

                // Practical 7: when the form really POSTs to the
                // PHP handler, let the browser submit it so the
                // server can validate and save. The "#" action is
                // only the offline demo, which shows this message.
                var postsToServer =
                    registerForm.getAttribute("action") !== "#";

                if (postsToServer) {
                    return;
                }

                successBox.textContent =
                    "Success! Your registration details are valid and ready. " +
                    "This is a frontend demo only \u2014 no account was " +
                    "created and nothing was saved to a server.";

                successBox.hidden = false;
                successBox.focus();

            }

        });

    }

});
