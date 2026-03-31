import { Calendar } from "@fullcalendar/core";
import dayGridPlugin from "@fullcalendar/daygrid";
import multiMonthPlugin from "@fullcalendar/multimonth";
import listPlugin from "@fullcalendar/list";
import timeGridPlugin from "@fullcalendar/timegrid";
import interactionPlugin from "@fullcalendar/interaction";

export function calendarInit() {
  const calendarEl = document.querySelector("#calendar");
  if (!calendarEl) return;

  const eventsUrl = calendarEl.dataset.eventsUrl;
  const storeUrl = calendarEl.dataset.storeUrl;
  const updateUrlBase = calendarEl.dataset.updateUrlBase;
  const destroyUrlBase = calendarEl.dataset.destroyUrlBase;
  const csrfToken = calendarEl.dataset.csrfToken;

  const getModalTitleEl = document.querySelector("#event-title");
  const getModalStartDateEl = document.querySelector("#event-start-date");
  const getModalEndDateEl = document.querySelector("#event-end-date");
  const getModalAddBtnEl = document.querySelector(".btn-add-event");
  const getModalUpdateBtnEl = document.querySelector(".btn-update-event");
  const getModalDeleteBtnEl = document.querySelector(".btn-delete-event");
  const getModalHeaderEl = document.querySelector("#eventModalLabel");
  const getModalSubtitleEl = document.querySelector("#eventModalSubtitle");
  const eventFormEl = document.querySelector("#event-form");
  const anniversaryDetailEl = document.querySelector("#anniversary-detail");

  function openModal() {
    const modal = document.getElementById("eventModal");
    if (modal) {
      modal.style.display = "flex";
      document.body.style.overflow = "hidden";
    }
  }

  function closeModal() {
    const modal = document.getElementById("eventModal");
    if (modal) {
      modal.style.display = "none";
      document.body.style.overflow = "";
    }
    resetModalFields();
  }

  function resetModalFields() {
    if (getModalTitleEl) getModalTitleEl.value = "";
    if (getModalStartDateEl) getModalStartDateEl.value = "";
    if (getModalEndDateEl) getModalEndDateEl.value = "";
    const checked = document.querySelector('input[name="event-level"]:checked');
    if (checked) checked.checked = false;
  }

  function showEventForm() {
    if (eventFormEl) eventFormEl.classList.remove("hidden");
    if (anniversaryDetailEl) anniversaryDetailEl.classList.add("hidden");
  }

  function showAnniversaryDetail() {
    if (eventFormEl) eventFormEl.classList.add("hidden");
    if (anniversaryDetailEl) anniversaryDetailEl.classList.remove("hidden");
  }

  function todayStr() {
    const d = new Date();
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, "0");
    const dd = String(d.getDate()).padStart(2, "0");
    return `${yyyy}-${mm}-${dd}`;
  }

  async function apiRequest(url, method, body = null) {
    const opts = {
      method,
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-CSRF-TOKEN": csrfToken,
        "X-Requested-With": "XMLHttpRequest",
      },
    };
    if (body) opts.body = JSON.stringify(body);
    const res = await fetch(url, opts);
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || `Request failed (${res.status})`);
    }
    return res.json();
  }

  // ── Calendar setup ──
  let calendar;

  const toolbarEl = document.getElementById("calendar-toolbar");
  const yearWrapEl = document.getElementById("calendar-year-wrap");
  const yearSelectEl = document.getElementById("calendar-year-select");
  const navTitleEl = document.getElementById("calendar-nav-title");
  const viewSelectEl = document.getElementById("calendar-view-select");
  const btnPrevEl = document.getElementById("calendar-btn-prev");
  const btnNextEl = document.getElementById("calendar-btn-next");
  const btnTodayEl = document.getElementById("calendar-btn-today");
  const toolbarAddEventEl = document.getElementById("calendar-toolbar-add-event");

  if (yearSelectEl) {
    const nowY = new Date().getFullYear();
    const fromY = nowY - 100;
    const toY = nowY + 30;
    for (let y = toY; y >= fromY; y -= 1) {
      const opt = document.createElement("option");
      opt.value = String(y);
      opt.textContent = String(y);
      yearSelectEl.appendChild(opt);
    }
  }

  function ensureYearOption(y) {
    if (!yearSelectEl) return;
    const s = String(y);
    if ([...yearSelectEl.options].some((o) => o.value === s)) return;
    const opt = document.createElement("option");
    opt.value = s;
    opt.textContent = s;
    yearSelectEl.insertBefore(opt, yearSelectEl.firstChild);
  }

  function syncToolbar() {
    if (!calendar) return;
    const view = calendar.view;
    const type = view.type;

    if (viewSelectEl) viewSelectEl.value = type;

    if (type === "multiMonthYear") {
      yearWrapEl?.classList.remove("hidden");
      navTitleEl?.classList.add("hidden");
      const y = view.currentStart.getFullYear();
      ensureYearOption(y);
      if (yearSelectEl && yearSelectEl.value !== String(y)) {
        yearSelectEl.value = String(y);
      }
    } else {
      yearWrapEl?.classList.add("hidden");
      navTitleEl?.classList.remove("hidden");
      if (navTitleEl) {
        if (type === "dayGridMonth") {
          navTitleEl.textContent = calendar.formatDate(view.currentStart, {
            month: "long",
            year: "numeric",
          });
        } else if (type === "timeGridWeek") {
          navTitleEl.textContent = calendar.formatRange(view.currentStart, view.currentEnd, {
            month: "short",
            day: "numeric",
            year: "numeric",
            separator: " – ",
          });
        } else if (type === "timeGridDay") {
          navTitleEl.textContent = calendar.formatDate(view.currentStart, {
            weekday: "long",
            month: "long",
            day: "numeric",
            year: "numeric",
          });
        } else {
          navTitleEl.textContent = "";
        }
      }
    }
  }

  const calendarSelect = (info) => {
    resetModalFields();
    showEventForm();
    if (getModalHeaderEl) getModalHeaderEl.textContent = "Add Event";
    if (getModalSubtitleEl) getModalSubtitleEl.textContent = "Schedule a personal event on your calendar";
    if (getModalAddBtnEl) getModalAddBtnEl.style.display = "flex";
    if (getModalUpdateBtnEl) getModalUpdateBtnEl.style.display = "none";
    if (getModalDeleteBtnEl) getModalDeleteBtnEl.classList.add("hidden");
    if (getModalStartDateEl) getModalStartDateEl.value = info.startStr;
    if (getModalEndDateEl) getModalEndDateEl.value = info.endStr || info.startStr;
    openModal();
  };

  const calendarAddEvent = () => {
    resetModalFields();
    showEventForm();
    if (getModalHeaderEl) getModalHeaderEl.textContent = "Add Event";
    if (getModalSubtitleEl) getModalSubtitleEl.textContent = "Schedule a personal event on your calendar";
    if (getModalAddBtnEl) getModalAddBtnEl.style.display = "flex";
    if (getModalUpdateBtnEl) getModalUpdateBtnEl.style.display = "none";
    if (getModalDeleteBtnEl) getModalDeleteBtnEl.classList.add("hidden");
    if (getModalStartDateEl) getModalStartDateEl.value = todayStr();
    openModal();
  };

  const calendarEventClick = (info) => {
    const eventObj = info.event;
    const props = eventObj.extendedProps;

    if (eventObj.url) {
      window.open(eventObj.url);
      info.jsEvent.preventDefault();
      return;
    }

    resetModalFields();

    if (props.type === "anniversary") {
      showAnniversaryDetail();
      if (getModalHeaderEl) getModalHeaderEl.textContent = "Death Anniversary";
      if (getModalSubtitleEl) getModalSubtitleEl.textContent = "This date is automatically generated from a memorial profile";

      const nameEl = document.querySelector("#anniversary-name");
      const dateLabel = document.querySelector("#anniversary-date-label");
      const photoEl = document.querySelector("#anniversary-photo");
      const linkEl = document.querySelector("#anniversary-link");

      if (nameEl) nameEl.textContent = eventObj.title;
      if (dateLabel) {
        const d = eventObj.start;
        const formatted = d ? d.toLocaleDateString("en-US", { weekday: "long", year: "numeric", month: "long", day: "numeric" }) : "";
        dateLabel.textContent = formatted;
      }
      if (photoEl) {
        if (props.profilePhoto) {
          photoEl.innerHTML = `<img src="${props.profilePhoto}" alt="" class="h-full w-full object-cover" />`;
        } else {
          photoEl.innerHTML = `<svg class="w-7 h-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>`;
        }
      }
      if (linkEl && props.memorialSlug) {
        linkEl.href = `/${props.memorialSlug}`;
      }

      if (getModalAddBtnEl) getModalAddBtnEl.style.display = "none";
      if (getModalUpdateBtnEl) getModalUpdateBtnEl.style.display = "none";
      if (getModalDeleteBtnEl) getModalDeleteBtnEl.classList.add("hidden");

      openModal();
      return;
    }

    // User event — show edit form
    showEventForm();
    if (getModalHeaderEl) getModalHeaderEl.textContent = "Edit Event";
    if (getModalSubtitleEl) getModalSubtitleEl.textContent = "Update or remove this event";
    if (getModalTitleEl) getModalTitleEl.value = eventObj.title;
    if (getModalStartDateEl) getModalStartDateEl.value = eventObj.startStr.split("T")[0];
    if (getModalEndDateEl) getModalEndDateEl.value = eventObj.endStr ? eventObj.endStr.split("T")[0] : "";

    const radioEl = document.querySelector(`input[value="${props.calendar}"]`);
    if (radioEl) radioEl.checked = true;

    if (getModalUpdateBtnEl) {
      getModalUpdateBtnEl.dataset.fcEventPublicId = eventObj.id;
      getModalUpdateBtnEl.style.display = "flex";
    }
    if (getModalDeleteBtnEl) {
      getModalDeleteBtnEl.dataset.fcEventPublicId = eventObj.id;
      getModalDeleteBtnEl.classList.remove("hidden");
    }
    if (getModalAddBtnEl) getModalAddBtnEl.style.display = "none";

    openModal();
  };

  calendar = new Calendar(calendarEl, {
    plugins: [dayGridPlugin, multiMonthPlugin, timeGridPlugin, listPlugin, interactionPlugin],
    selectable: true,
    initialView: "dayGridMonth",
    headerToolbar: false,
    views: {
      multiMonthYear: {
        multiMonthMaxColumns: 3,
        multiMonthMinWidth: 200,
      },
    },
    datesSet() {
      syncToolbar();
    },
    events: (fetchInfo, successCallback, failureCallback) => {
      fetch(eventsUrl, {
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
      })
        .then((res) => res.json())
        .then((events) => successCallback(events))
        .catch((err) => failureCallback(err));
    },
    select: calendarSelect,
    eventClick: calendarEventClick,
    displayEventTime: false,
    eventContent(eventInfo) {
      const props = eventInfo.event.extendedProps;
      const colorClass = `fc-bg-${(props.calendar || "primary").toLowerCase()}`;
      const isAnniversary = props.type === "anniversary";
      const icon = isAnniversary
        ? `<svg class="w-3.5 h-3.5 mr-1 inline-block shrink-0 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`
        : "";
      return {
        html: `
          <div class="event-fc-color flex fc-event-main ${colorClass} p-1 rounded-sm">
            <div class="fc-daygrid-event-dot"></div>
            <div class="fc-event-title flex items-center">${icon}${eventInfo.event.title}</div>
          </div>
        `,
      };
    },
  });

  // ── Add event ──
  if (getModalAddBtnEl) {
    getModalAddBtnEl.addEventListener("click", async () => {
      const title = getModalTitleEl?.value?.trim();
      const startDate = getModalStartDateEl?.value;
      const endDate = getModalEndDateEl?.value || null;
      const colorEl = document.querySelector('input[name="event-level"]:checked');
      const color = colorEl ? colorEl.value : "Primary";

      if (!title || !startDate) return;

      getModalAddBtnEl.disabled = true;
      getModalAddBtnEl.textContent = "Saving...";

      try {
        const eventData = await apiRequest(storeUrl, "POST", {
          title,
          start_date: startDate,
          end_date: endDate,
          color,
        });
        calendar.addEvent(eventData);
        closeModal();
      } catch (e) {
        alert(e.message || "Failed to add event. Please try again.");
      } finally {
        getModalAddBtnEl.disabled = false;
        getModalAddBtnEl.textContent = "Add Event";
      }
    });
  }

  // ── Update event ──
  if (getModalUpdateBtnEl) {
    getModalUpdateBtnEl.addEventListener("click", async () => {
      const eventId = getModalUpdateBtnEl.dataset.fcEventPublicId;
      const title = getModalTitleEl?.value?.trim();
      const startDate = getModalStartDateEl?.value;
      const endDate = getModalEndDateEl?.value || null;
      const colorEl = document.querySelector('input[name="event-level"]:checked');
      const color = colorEl ? colorEl.value : "Primary";

      if (!title || !startDate) return;

      getModalUpdateBtnEl.disabled = true;
      getModalUpdateBtnEl.textContent = "Saving...";

      try {
        const eventData = await apiRequest(`${updateUrlBase}/${eventId}`, "PUT", {
          title,
          start_date: startDate,
          end_date: endDate,
          color,
        });

        const existing = calendar.getEventById(eventId);
        if (existing) existing.remove();
        calendar.addEvent(eventData);
        closeModal();
      } catch (e) {
        alert(e.message || "Failed to update event. Please try again.");
      } finally {
        getModalUpdateBtnEl.disabled = false;
        getModalUpdateBtnEl.textContent = "Update Changes";
      }
    });
  }

  // ── Delete event ──
  if (getModalDeleteBtnEl) {
    getModalDeleteBtnEl.addEventListener("click", async () => {
      const eventId = getModalDeleteBtnEl.dataset.fcEventPublicId;
      if (!confirm("Are you sure you want to delete this event?")) return;

      getModalDeleteBtnEl.disabled = true;
      getModalDeleteBtnEl.textContent = "Deleting...";

      try {
        await apiRequest(`${destroyUrlBase}/${eventId}`, "DELETE");
        const existing = calendar.getEventById(eventId);
        if (existing) existing.remove();
        closeModal();
      } catch (e) {
        alert(e.message || "Failed to delete event. Please try again.");
      } finally {
        getModalDeleteBtnEl.disabled = false;
        getModalDeleteBtnEl.textContent = "Delete";
      }
    });
  }

  if (toolbarEl) {
    btnPrevEl?.addEventListener("click", () => calendar.prev());
    btnNextEl?.addEventListener("click", () => calendar.next());
    btnTodayEl?.addEventListener("click", () => calendar.today());
    viewSelectEl?.addEventListener("change", () => {
      const v = viewSelectEl.value;
      if (v && calendar.view.type !== v) calendar.changeView(v);
    });
    yearSelectEl?.addEventListener("change", () => {
      const y = parseInt(yearSelectEl.value, 10);
      if (!Number.isNaN(y)) calendar.gotoDate(new Date(y, 0, 1));
    });
    toolbarAddEventEl?.addEventListener("click", () => calendarAddEvent());
  }

  calendar.render();
  syncToolbar();

  // ── Modal close handlers ──
  document.querySelectorAll(".modal-close-btn").forEach((btn) => {
    btn.addEventListener("click", closeModal);
  });

  window.addEventListener("click", (event) => {
    const modal = document.getElementById("eventModal");
    if (event.target === modal) closeModal();
  });
}

export default calendarInit;
