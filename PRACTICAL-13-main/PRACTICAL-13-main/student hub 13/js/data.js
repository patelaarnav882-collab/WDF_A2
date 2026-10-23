/* =====================================================
   STUDENTHUB - PRACTICAL 6
   Fetch API + search, filter, sort and pagination.

   Data flow (same on every page):
   JSON file --fetch()--> records --> filter() -->
   sort() --> slice() for the current page --> render.

   Pure helper functions are attached to
   `StudentHubData` so they can be unit-tested.
   Nothing below uses the network except loadDataset().
   ===================================================== */

(function () {

    "use strict";

    var rootScope =
        typeof window !== "undefined" ? window : globalThis;


    /* =====================================================
       PURE HELPERS (no DOM, no network)
       ===================================================== */

    // Escape text before putting it into innerHTML so that
    // data can never break the page or inject markup.
    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    // Split an ISO date ("2026-11-15") without timezone shifts.
    function splitIsoDate(isoDate) {

        var parts = String(isoDate).split("-");

        return {
            year: parts[0] || "",
            month: parts[1] || "",
            day: parts[2] || ""
        };

    }

    var MONTH_NAMES = [
        "January", "February", "March", "April",
        "May", "June", "July", "August",
        "September", "October", "November", "December"
    ];

    var MONTH_SHORT = [
        "JAN", "FEB", "MAR", "APR", "MAY", "JUN",
        "JUL", "AUG", "SEP", "OCT", "NOV", "DEC"
    ];

    function formatDateLong(isoDate) {

        var parts = splitIsoDate(isoDate);
        var monthIndex = parseInt(parts.month, 10) - 1;
        var monthName = MONTH_NAMES[monthIndex] || "";

        return (
            parseInt(parts.day, 10) + " " +
            monthName + " " + parts.year
        );

    }

    function formatDateBadge(isoDate) {

        var parts = splitIsoDate(isoDate);
        var monthIndex = parseInt(parts.month, 10) - 1;

        return {
            day: String(parseInt(parts.day, 10) || ""),
            month: MONTH_SHORT[monthIndex] || ""
        };

    }

    // Search across the given keys (case-insensitive) and
    // then narrow by an exact category match.
    function filterRecords(records, options) {

        var query = (options.query || "").trim().toLowerCase();
        var category = options.category || "";
        var keys = options.keys || [];

        return records.filter(function (record) {

            if (category && record[options.categoryKey] !== category) {
                return false;
            }

            if (!query) {
                return true;
            }

            return keys.some(function (key) {
                return String(record[key] || "")
                    .toLowerCase()
                    .indexOf(query) !== -1;
            });

        });

    }

    // Generic sort by a value function, "asc" or "desc".
    function sortRecords(records, getValue, direction) {

        var copy = records.slice();

        copy.sort(function (a, b) {

            var valueA = getValue(a);
            var valueB = getValue(b);

            if (valueA < valueB) {
                return direction === "desc" ? 1 : -1;
            }

            if (valueA > valueB) {
                return direction === "desc" ? -1 : 1;
            }

            return 0;

        });

        return copy;

    }

    // Slice out one page; clamps page into the valid range.
    function paginate(records, page, perPage) {

        var totalPages = Math.max(1, Math.ceil(records.length / perPage));
        var currentPage = Math.min(Math.max(1, page), totalPages);

        var start = (currentPage - 1) * perPage;

        return {
            pageItems: records.slice(start, start + perPage),
            totalPages: totalPages,
            currentPage: currentPage,
            total: records.length,
            from: records.length === 0 ? 0 : start + 1,
            to: Math.min(start + perPage, records.length)
        };

    }

    function uniqueValues(records, key) {

        var seen = {};
        var values = [];

        records.forEach(function (record) {

            var value = record[key];

            if (value !== undefined && value !== "" && !seen[value]) {
                seen[value] = true;
                values.push(value);
            }

        });

        return values.sort();

    }

    rootScope.StudentHubData = {
        escapeHtml: escapeHtml,
        splitIsoDate: splitIsoDate,
        formatDateLong: formatDateLong,
        formatDateBadge: formatDateBadge,
        filterRecords: filterRecords,
        sortRecords: sortRecords,
        paginate: paginate,
        uniqueValues: uniqueValues
    };


    /* =====================================================
       CACHE (Advanced extension: offline-like display).
       The latest good response is kept in localStorage.
       If fetch() fails we fall back to that copy and say
       exactly when it was saved, so stale data is visible.
       ===================================================== */

    function cacheKey(name) {
        return "studentHubCache_" + name;
    }

    function readCache(name) {

        try {

            var raw = localStorage.getItem(cacheKey(name));

            if (!raw) {
                return null;
            }

            var parsed = JSON.parse(raw);

            if (!parsed || !Array.isArray(parsed.records)) {
                return null;
            }

            return parsed;

        } catch (error) {
            return null;
        }

    }

    function writeCache(name, records) {

        try {

            localStorage.setItem(
                cacheKey(name),
                JSON.stringify({
                    savedAt: new Date().toISOString(),
                    records: records
                })
            );

        } catch (error) {
            /* Storage full or unavailable: caching is optional. */
        }

    }


    /* =====================================================
       FETCH LOADER with loading + error + cache states.
       ===================================================== */

    function loadDataset(name, url) {

        return fetch(url).then(function (response) {

            if (!response.ok) {
                throw new Error("HTTP " + response.status);
            }

            return response.json();

        }).then(function (records) {

            if (!Array.isArray(records)) {
                throw new Error("Invalid dataset");
            }

            writeCache(name, records);

            return { records: records, fromCache: false, error: null };

        }).catch(function (error) {

            var cached = readCache(name);

            if (cached) {
                return {
                    records: cached.records,
                    fromCache: true,
                    savedAt: cached.savedAt,
                    error: null
                };
            }

            return { records: [], fromCache: false, error: error };

        });

    }


    /* =====================================================
       GENERIC DATA VIEW (wires controls to one dataset).
       ===================================================== */

    function initDataView(config) {

        var grid = document.getElementById(config.gridId);

        if (!grid) {
            return;
        }

        var searchInput = document.getElementById(config.searchId);
        var categorySelect = document.getElementById(config.categoryId);
        var sortSelect = document.getElementById(config.sortId);
        var statusBox = document.getElementById(config.statusId);
        var emptyBox = document.getElementById(config.emptyId);
        var countBox = document.getElementById(config.countId);
        var pageInfo = document.getElementById(config.pageId);
        var prevButton = document.getElementById(config.prevId);
        var nextButton = document.getElementById(config.nextId);

        var state = {
            all: [],
            query: "",
            category: "",
            sort: config.defaultSort,
            page: 1
        };

        function showStatus(message, isError) {

            if (!statusBox) {
                return;
            }

            if (!message) {
                statusBox.hidden = true;
                statusBox.textContent = "";
                return;
            }

            statusBox.hidden = false;
            statusBox.textContent = message;
            statusBox.classList.toggle("data-error", !!isError);

        }

        function buildCategoryOptions() {

            if (!categorySelect) {
                return;
            }

            var values = uniqueValues(state.all, config.categoryKey);

            // Keep the "All ..." placeholder, rebuild the rest.
            categorySelect.querySelectorAll("option[value]:not([value=''])")
                .forEach(function (option) {
                    option.remove();
                });

            values.forEach(function (value) {

                var option = document.createElement("option");
                option.value = value;
                option.textContent = value;
                categorySelect.appendChild(option);

            });

        }

        function render() {

            var filtered = filterRecords(state.all, {
                query: state.query,
                keys: config.searchKeys,
                category: state.category,
                categoryKey: config.categoryKey
            });

            var sortOption = config.sortOptions[state.sort] ||
                config.sortOptions[config.defaultSort];

            var sorted = sortRecords(
                filtered,
                sortOption.getValue,
                sortOption.direction
            );

            var page = paginate(sorted, state.page, config.perPage);
            state.page = page.currentPage;

            config.renderItems(grid, page.pageItems);

            if (emptyBox) {
                emptyBox.hidden = page.total !== 0;
            }

            if (countBox) {
                countBox.textContent = page.total === 0
                    ? "No " + config.itemLabel + " found."
                    : "Showing " + page.from + "\u2013" + page.to +
                      " of " + page.total + " " + config.itemLabel + ".";
            }

            if (pageInfo) {
                pageInfo.textContent =
                    "Page " + page.currentPage + " of " + page.totalPages;
            }

            if (prevButton) {
                prevButton.disabled = page.currentPage <= 1;
            }

            if (nextButton) {
                nextButton.disabled =
                    page.currentPage >= page.totalPages;
            }

            if (config.afterRender) {
                config.afterRender(grid, page.pageItems);
            }

        }

        function resetPageAndRender() {
            state.page = 1;
            render();
        }

        if (searchInput) {
            searchInput.addEventListener("input", function () {
                state.query = searchInput.value;
                resetPageAndRender();
            });
        }

        if (categorySelect) {
            categorySelect.addEventListener("change", function () {
                state.category = categorySelect.value;
                resetPageAndRender();
            });
        }

        if (sortSelect) {
            sortSelect.addEventListener("change", function () {
                state.sort = sortSelect.value;
                resetPageAndRender();
            });
        }

        if (prevButton) {
            prevButton.addEventListener("click", function () {
                state.page -= 1;
                render();
            });
        }

        if (nextButton) {
            nextButton.addEventListener("click", function () {
                state.page += 1;
                render();
            });
        }

        showStatus("Loading " + config.itemLabel + "\u2026", false);

        loadDataset(config.cacheName, config.url).then(function (result) {

            if (result.error) {
                showStatus(
                    "Could not load " + config.itemLabel + ". " +
                    "Check your connection and try again.",
                    true
                );
                state.all = [];
                buildCategoryOptions();
                render();
                return;
            }

            state.all = result.records;
            buildCategoryOptions();

            if (result.fromCache) {

                var when = "";
                try {
                    when = new Date(result.savedAt).toLocaleString();
                } catch (error) {
                    when = String(result.savedAt || "");
                }

                showStatus(
                    "You are offline. Showing the saved copy from " +
                    when + ".",
                    false
                );

            } else {
                showStatus(null, false);
            }

            resetPageAndRender();

        });

    }


    /* =====================================================
       PAGE CONFIGURATIONS
       ===================================================== */

    function eventCardHtml(event) {

        var badge = formatDateBadge(event.date);
        var longDate = formatDateLong(event.date);
        var details =
            event.description + " Venue: " + event.location +
            ". Date: " + longDate + ".";

        return (
            '<article class="card event-page-card">' +
                '<div class="event-card-date" aria-hidden="true">' +
                    '<span class="date-day">' +
                        escapeHtml(badge.day) +
                    "</span>" +
                    '<span class="date-month">' +
                        escapeHtml(badge.month) +
                    "</span>" +
                "</div>" +
                '<div class="event-card-content">' +
                    '<span class="event-type">' +
                        escapeHtml(String(event.category).toUpperCase()) +
                    "</span>" +
                    "<h3>" + escapeHtml(event.title) + "</h3>" +
                    '<p class="event-location">' +
                        escapeHtml(event.location) +
                    "</p>" +
                    '<p class="event-date">' +
                        "<time datetime=\"" + escapeHtml(event.date) + "\">" +
                            escapeHtml(longDate) +
                        "</time>" +
                    "</p>" +
                    "<p>" + escapeHtml(event.description) + "</p>" +
                    '<button class="text-link modal-open" type="button"' +
                        ' data-title="' + escapeHtml(event.title) + '"' +
                        ' data-content="' + escapeHtml(details) + '">' +
                        "Learn More" +
                    "</button>" +
                "</div>" +
            "</article>"
        );

    }

    function studentRowHtml(student) {

        var isActive =
            String(student.status).toLowerCase() === "active";

        return (
            "<tr>" +
                "<td>" + escapeHtml(student.id) + "</td>" +
                "<td>" + escapeHtml(student.name) + "</td>" +
                "<td>" + escapeHtml(student.course) + "</td>" +
                "<td>" + escapeHtml(student.year) + "</td>" +
                "<td>" + escapeHtml(student.department) + "</td>" +
                "<td>" + escapeHtml(student.email) + "</td>" +
                "<td>" +
                    '<span class="student-status ' +
                        (isActive ? "active" : "inactive") + '">' +
                        escapeHtml(student.status) +
                    "</span>" +
                "</td>" +
            "</tr>"
        );

    }

    function faqCardHtml(faq) {

        return (
            '<article class="faq-card">' +
                "<h3>" + escapeHtml(faq.question) + "</h3>" +
                "<p>" + escapeHtml(faq.answer) + "</p>" +
            "</article>"
        );

    }

    function initPageViews() {

        /* ----- Events page ----- */
        initDataView({
            gridId: "eventGrid",
            searchId: "eventSearch",
            categoryId: "eventCategory",
            sortId: "eventSort",
            statusId: "eventStatus",
            emptyId: "eventEmpty",
            countId: "eventCount",
            pageId: "eventPageInfo",
            prevId: "eventPrev",
            nextId: "eventNext",
            url: "../data/events.json",
            cacheName: "events",
            itemLabel: "events",
            perPage: 6,
            searchKeys: ["title", "description", "location", "category"],
            categoryKey: "category",
            defaultSort: "date-asc",
            sortOptions: {
                "date-asc": {
                    getValue: function (e) { return e.date; },
                    direction: "asc"
                },
                "date-desc": {
                    getValue: function (e) { return e.date; },
                    direction: "desc"
                },
                "title-az": {
                    getValue: function (e) {
                        return String(e.title).toLowerCase();
                    },
                    direction: "asc"
                }
            },
            renderItems: function (grid, items) {
                grid.innerHTML = items.map(eventCardHtml).join("");
            },
            afterRender: function (grid) {
                // Details buttons are re-created on every render,
                // so open the shared Practical 4 modal directly.
                grid.querySelectorAll(".modal-open").forEach(function (btn) {
                    btn.addEventListener("click", function () {
                        if (
                            window.StudentHub &&
                            window.StudentHub.openEventModal
                        ) {
                            window.StudentHub.openEventModal(
                                btn.getAttribute("data-title"),
                                btn.getAttribute("data-content")
                            );
                        }
                    });
                });
            }
        });

        /* ----- Students page ----- */
        initDataView({
            gridId: "studentRows",
            searchId: "studentSearch",
            categoryId: "studentCourse",
            sortId: "studentSort",
            statusId: "studentStatus",
            emptyId: "studentEmpty",
            countId: "studentCount",
            pageId: "studentPageInfo",
            prevId: "studentPrev",
            nextId: "studentNext",
            url: "../data/students.json",
            cacheName: "students",
            itemLabel: "students",
            perPage: 5,
            searchKeys: ["name", "id", "course", "department", "email"],
            categoryKey: "course",
            defaultSort: "name-asc",
            sortOptions: {
                "name-asc": {
                    getValue: function (s) {
                        return String(s.name).toLowerCase();
                    },
                    direction: "asc"
                },
                "name-desc": {
                    getValue: function (s) {
                        return String(s.name).toLowerCase();
                    },
                    direction: "desc"
                },
                "year-asc": {
                    getValue: function (s) { return Number(s.year); },
                    direction: "asc"
                }
            },
            renderItems: function (tbody, items) {
                tbody.innerHTML = items.map(studentRowHtml).join("");
            }
        });

        /* ----- FAQ page ----- */
        initDataView({
            gridId: "faqGrid",
            searchId: "faqSearch",
            categoryId: "faqCategory",
            sortId: "faqSort",
            statusId: "faqStatus",
            emptyId: "faqEmpty",
            countId: "faqCount",
            pageId: "faqPageInfo",
            prevId: "faqPrev",
            nextId: "faqNext",
            url: "../data/faqs.json",
            cacheName: "faqs",
            itemLabel: "questions",
            perPage: 6,
            searchKeys: ["question", "answer", "category"],
            categoryKey: "category",
            defaultSort: "default",
            sortOptions: {
                "default": {
                    getValue: function (f) { return f.id; },
                    direction: "asc"
                },
                "question-az": {
                    getValue: function (f) {
                        return String(f.question).toLowerCase();
                    },
                    direction: "asc"
                },
                "question-za": {
                    getValue: function (f) {
                        return String(f.question).toLowerCase();
                    },
                    direction: "desc"
                }
            },
            renderItems: function (grid, items) {
                grid.innerHTML = items.map(faqCardHtml).join("");
            },
            afterRender: function (grid) {
                // Reuse the Practical 4 collapsible behaviour.
                if (
                    window.StudentHub &&
                    window.StudentHub.bindFaqCards
                ) {
                    window.StudentHub.bindFaqCards(grid);
                }
            }
        });

    }

    // Skip DOM wiring when loaded in a non-browser context
    // (lets Node import the pure helpers for testing).
    if (typeof document !== "undefined") {
        document.addEventListener("DOMContentLoaded", initPageViews);
    }

})();
