/* =====================================================
   STUDENTHUB - PRACTICAL 13
   Fetch-based student CRUD. Every list/add/edit/delete
   talks to ../api/students.php as JSON and re-renders
   the table — the browser page never reloads.

   Flow per action:
     user event -> fetch() -> response.ok? -> .json() ->
     {success,message,data} -> render message + rows.
   ===================================================== */
(function () {
    "use strict";

    var tbody = document.getElementById("ajaxRows");
    if (!tbody) {
        return; // not the AJAX page; nothing to wire up
    }

    var API_URL = "../api/students.php";
    var PER_PAGE = 8;

    var searchInput = document.getElementById("ajaxSearch");
    var statusBox = document.getElementById("ajaxStatus");
    var countBox = document.getElementById("ajaxCount");
    var emptyBox = document.getElementById("ajaxEmpty");
    var pageInfo = document.getElementById("ajaxPageInfo");
    var prevButton = document.getElementById("ajaxPrev");
    var nextButton = document.getElementById("ajaxNext");
    var addButton = document.getElementById("ajaxAdd");
    var modal = document.getElementById("studentModal");
    var modalX = document.getElementById("studentModalX");
    var modalTitle = document.getElementById("studentModalTitle");
    var form = document.getElementById("studentForm");
    var formStatus = document.getElementById("ajaxFormStatus");
    var submitButton = document.getElementById("studentSubmit");

    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var CSRF_TOKEN = csrfMeta ? csrfMeta.getAttribute("content") : "";

    var state = { query: "", page: 1, totalPages: 1 };
    var searchTimer = null;

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function showStatus(message, isError) {
        if (!message) {
            statusBox.hidden = true;
            statusBox.textContent = "";
            return;
        }
        statusBox.hidden = false;
        statusBox.textContent = message;
        statusBox.classList.toggle("data-error", !!isError);
    }

    // One place for all API calls: checks response.ok, parses
    // JSON, and throws a readable Error on any failure.
    function apiRequest(method, query, payload) {
        var url = API_URL + (query || "");
        var options = {
            method: method,
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-Token": CSRF_TOKEN
            }
        };
        if (payload !== undefined) {
            options.body = JSON.stringify(payload);
        }
        showStatus("Loading students\u2026", false);
        return fetch(url, options).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || !data || data.success !== true) {
                    var err = new Error(
                        (data && data.message) ||
                        ("Request failed (HTTP " + response.status + ")")
                    );
                    err.data = data;
                    err.status = response.status;
                    throw err;
                }
                return data.data;
            }, function () {
                throw new Error("Server returned an unreadable response.");
            });
        }, function () {
            throw new Error("Could not reach the server. Check your connection.");
        });
    }

    function renderRows(students) {
        tbody.innerHTML = students.map(function (s) {
            var year = s.academic_year === null ? "\u2014" : s.academic_year;
            return (
                "<tr>" +
                "<td>" + escapeHtml(s.enrollment_id || "\u2014") + "</td>" +
                "<td>" + escapeHtml(s.name) + "</td>" +
                "<td>" + escapeHtml(s.email) + "</td>" +
                "<td>" + escapeHtml(s.mobile) + "</td>" +
                "<td>" + escapeHtml(s.course) + "</td>" +
                "<td>" + escapeHtml(year) + "</td>" +
                "<td>" +
                '<button type="button" class="btn btn-outline btn-small" data-edit="' +
                s.id + '">Edit</button> ' +
                '<button type="button" class="btn btn-danger btn-small" data-delete="' +
                s.id + '">Delete</button>' +
                "</td>" +
                "</tr>"
            );
        }).join("");
    }

    function loadStudents() {
        var params = "?page=" + state.page + "&per_page=" + PER_PAGE;
        if (state.query !== "") {
            params += "&q=" + encodeURIComponent(state.query);
        }
        return apiRequest("GET", params).then(function (data) {
            showStatus(null, false);
            renderRows(data.students || []);
            state.page = data.page;
            state.totalPages = data.total_pages;
            var total = data.total;
            countBox.textContent = total === 0
                ? "No students found. Try a different search."
                : "Total students: " + total + ". Showing page " +
                  data.page + " of " + data.total_pages + ".";
            emptyBox.hidden = total !== 0;
            pageInfo.textContent = "Page " + data.page + " of " + data.total_pages;
            prevButton.disabled = data.page <= 1;
            nextButton.disabled = data.page >= data.total_pages;
        }).catch(function (err) {
            showStatus(err.message, true);
        });
    }

    /* ----- modal form ----- */

    function fieldError(id, message) {
        var box = document.getElementById("e-" + id);
        if (!box) {
            return;
        }
        if (!message) {
            box.hidden = true;
            box.textContent = "";
            return;
        }
        box.hidden = false;
        box.textContent = message;
    }

    function clearFormErrors() {
        ["enrollment_id", "full_name", "email", "phone",
         "course", "year", "gender"].forEach(function (id) {
            fieldError(id, null);
        });
        formStatus.hidden = true;
        formStatus.textContent = "";
    }

    function openModal(title, submitLabel) {
        modalTitle.textContent = title;
        submitButton.textContent = submitLabel;
        clearFormErrors();
        modal.hidden = false;
        document.getElementById("f-enrollment").focus();
    }

    function closeModal() {
        modal.hidden = true;
        form.reset();
        document.getElementById("f-id").value = "";
    }

    addButton.addEventListener("click", function () {
        form.reset();
        document.getElementById("f-id").value = "";
        openModal("Add Student", "Add Student");
    });

    modalX.addEventListener("click", closeModal);

    modal.addEventListener("click", function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && !modal.hidden) {
            closeModal();
        }
    });

    // Edit buttons are re-created on every render, so listen
    // once on the table body (event delegation).
    tbody.addEventListener("click", function (event) {
        var editButton = event.target.closest("[data-edit]");
        var deleteButton = event.target.closest("[data-delete]");

        if (editButton) {
            apiRequest("GET", "?id=" + encodeURIComponent(editButton.getAttribute("data-edit")))
                .then(function (s) {
                    document.getElementById("f-id").value = s.id;
                    document.getElementById("f-enrollment").value = s.enrollment_id || "";
                    document.getElementById("f-name").value = s.name || "";
                    document.getElementById("f-email").value = s.email || "";
                    document.getElementById("f-phone").value = s.mobile || "";
                    document.getElementById("f-course").value = s.course || "";
                    document.getElementById("f-year").value =
                        s.academic_year === null ? "" : String(s.academic_year);
                    var radios = form.querySelectorAll('input[name="f-gender"]');
                    radios.forEach(function (r) {
                        r.checked = r.value === s.gender;
                    });
                    openModal("Edit Student", "Save Changes");
                })
                .catch(function (err) {
                    showStatus(err.message, true);
                });
            return;
        }

        if (deleteButton) {
            var name = deleteButton.closest("tr").children[1].textContent;
            if (!window.confirm('Delete "' + name.trim() + '"? This cannot be undone.')) {
                return;
            }
            apiRequest("DELETE", "", { id: deleteButton.getAttribute("data-delete") })
                .then(function () {
                    showStatus("Student deleted successfully.", false);
                    return loadStudents();
                })
                .catch(function (err) {
                    showStatus(err.message, true);
                });
        }
    });

    form.addEventListener("submit", function (event) {
        event.preventDefault();
        clearFormErrors();

        var id = document.getElementById("f-id").value;
        var genderChecked = form.querySelector('input[name="f-gender"]:checked');
        var payload = {
            enrollment_id: document.getElementById("f-enrollment").value.trim(),
            full_name: document.getElementById("f-name").value.trim(),
            email: document.getElementById("f-email").value.trim(),
            phone: document.getElementById("f-phone").value.trim(),
            course: document.getElementById("f-course").value,
            year: document.getElementById("f-year").value,
            gender: genderChecked ? genderChecked.value : ""
        };

        var request = id === ""
            ? apiRequest("POST", "", payload)
            : apiRequest("PUT", "", Object.assign({ id: id }, payload));

        request.then(function () {
            closeModal();
            showStatus(
                id === "" ? "Student added successfully."
                          : "Student updated successfully.",
                false
            );
            return loadStudents();
        }).catch(function (err) {
            var errors = err.data && err.data.data && err.data.data.errors;
            if (errors) {
                // Server keys match the e-* message boxes exactly.
                Object.keys(errors).forEach(function (key) {
                    fieldError(key, errors[key]);
                });
                formStatus.hidden = false;
                formStatus.textContent = err.message;
                return;
            }
            formStatus.hidden = false;
            formStatus.textContent = err.message;
        });
    });

    /* ----- search (debounced) + pagination ----- */

    searchInput.addEventListener("input", function () {
        if (searchTimer !== null) {
            clearTimeout(searchTimer);
        }
        searchTimer = setTimeout(function () {
            state.query = searchInput.value.trim();
            state.page = 1;
            loadStudents();
        }, 300);
    });

    prevButton.addEventListener("click", function () {
        if (state.page > 1) {
            state.page -= 1;
            loadStudents();
        }
    });

    nextButton.addEventListener("click", function () {
        if (state.page < state.totalPages) {
            state.page += 1;
            loadStudents();
        }
    });

    loadStudents();
})();
