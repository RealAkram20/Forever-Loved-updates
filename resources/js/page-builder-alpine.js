import Sortable from "sortablejs";

function deepClone(obj) {
  try {
    return JSON.parse(JSON.stringify(obj));
  } catch {
    return {};
  }
}

function defaultPropsForType(definitions, type) {
  const def = definitions.find((d) => d.type === type);
  return def ? deepClone(def.defaultProps) : {};
}

const defaultSpacingBox = () => ({ top: "0", right: "0", bottom: "0", left: "0", unit: "px", linked: true });
const defaultStyleBox = () => ({ background_color: "", text_color: "", text_align: "" });
const defaultAdvancedBox = () => ({ css_id: "", css_class: "", hidden: false });

function ensureSpacing(props) {
  if (!props._spacing || typeof props._spacing !== "object") {
    props._spacing = { margin: defaultSpacingBox(), padding: defaultSpacingBox() };
  } else {
    if (!props._spacing.margin || typeof props._spacing.margin !== "object") props._spacing.margin = defaultSpacingBox();
    if (!props._spacing.padding || typeof props._spacing.padding !== "object") props._spacing.padding = defaultSpacingBox();
  }
  if (!props._style || typeof props._style !== "object") {
    props._style = defaultStyleBox();
  } else {
    props._style = { ...defaultStyleBox(), ...props._style };
  }
  if (!props._advanced || typeof props._advanced !== "object") {
    props._advanced = defaultAdvancedBox();
  } else {
    props._advanced = { ...defaultAdvancedBox(), ...props._advanced };
  }
  return props;
}

function normalizeIncomingWidget(definitions, w, index) {
  const type = w.type || "";
  const def = definitions.find((d) => d.type === type);
  const base = def ? deepClone(def.defaultProps) : {};
  const props = typeof w.props === "object" && w.props !== null ? { ...base, ...w.props } : base;
  ensureSpacing(props);
  const id =
    typeof w.id === "string" && /^w_[a-zA-Z0-9_-]+$/.test(w.id)
      ? w.id
      : "w_" + crypto.randomUUID().replace(/-/g, "").slice(0, 10);
  return { id, type, order: index, props };
}

function slugify(text) {
  return String(text || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 100);
}

function extractPropText(value) {
  if (!value) return "";
  const s = String(value);
  if (s.includes("<")) {
    try {
      const d = document.createElement("div");
      d.innerHTML = s;
      return (d.textContent || "").replace(/\s+/g, " ").trim();
    } catch { return ""; }
  }
  return s.replace(/\s+/g, " ").trim();
}

function extractTextFromWidgets(widgets, definitions) {
  const parts = [];
  for (const w of widgets) {
    const def = definitions?.find((d) => d.type === w.type);
    const fields = def?.previewFields || [];
    const p = w.props || {};
    for (const key of fields) {
      const t = extractPropText(p[key]);
      if (t) parts.push(t);
    }
  }
  return parts.join(" ").replace(/\s+/g, " ").trim();
}

function stripHtmlPreview(html) {
  if (typeof html !== "string" || !html) return "";
  try {
    const d = document.createElement("div");
    d.innerHTML = html;
    return (d.textContent || "").replace(/\s+/g, " ").trim();
  } catch {
    return "";
  }
}

document.addEventListener("alpine:init", () => {
  Alpine.data("pageBuilder", () => ({
    definitions: [],
    widgets: [],
    selectedId: null,
    saving: false,
    createMode: false,
    slugEditable: false,
    originalSlug: "",
    cmsPathPrefix: "/p",
    _publicUrl: "",
    _publicUrlBase: "",
    _slugManuallyEdited: false,
    _metaTitleManuallyEdited: false,
    _metaDescManuallyEdited: false,
    _canvasSortable: null,
    seoOpen: false,
    seoSaving: false,
    navOpen: false,
    dirty: false,
    jsonErrors: {},
    previewDevice: "desktop",
    previewLoading: false,
    previewFailed: false,
    _previewTimer: null,
    _previewInFlight: false,
    _previewQueued: false,
    _previewScroll: 0,
    seo: {
      slug: "",
      title: "",
      meta_title: "",
      meta_description: "",
      is_published: true,
      og_image_url: null,
      remove_og_image: false,
    },

    get selectedWidget() {
      return this.widgets.find((w) => w.id === this.selectedId) ?? null;
    },

    get selectedDefinition() {
      const w = this.selectedWidget;
      if (!w) return null;
      return this.definitions.find((d) => d.type === w.type) ?? null;
    },

    get pageUrl() {
      if (this._publicUrl) return this._publicUrl;
      const slug = this.seo.slug || "your-slug";
      return this._publicUrlBase
        ? this._publicUrlBase + slug
        : this.cmsPathPrefix + "/" + slug;
    },

    init() {
      const cfg = window.__PAGE_BUILDER__;
      if (!cfg) {
        console.warn("[PageBuilder] window.__PAGE_BUILDER__ is not set.");
        return;
      }
      try {
        this.createMode = !!cfg.createMode;
        this.slugEditable = !!cfg.slugEditable;
        this.originalSlug =
          cfg.originalSlug != null ? String(cfg.originalSlug) : "";
        this.cmsPathPrefix =
          cfg.cmsPathPrefix != null ? String(cfg.cmsPathPrefix) : "/p";
        this._publicUrl = cfg.publicUrl ?? "";
        this._publicUrlBase = cfg.publicUrlBase ?? "";
        this.definitions = cfg.definitions || [];
        const raw = cfg.initial?.widgets ?? [];
        this.widgets = raw.map((w, i) => {
          try {
            return normalizeIncomingWidget(this.definitions, w, i);
          } catch (wErr) {
            console.error("[PageBuilder] Bad widget at index", i, w, wErr);
            return {
              id: "w_err_" + i,
              type: w.type || "unknown",
              order: i,
              props: {},
            };
          }
        });
        if (cfg.seo && typeof cfg.seo === "object") {
          this.seo = {
            slug: cfg.seo.slug != null ? String(cfg.seo.slug) : "",
            title: cfg.seo.title ?? "",
            meta_title: cfg.seo.meta_title ?? "",
            meta_description: cfg.seo.meta_description ?? "",
            is_published: !!cfg.seo.is_published,
            og_image_url: cfg.seo.og_image_url ?? null,
            remove_og_image: false,
          };
        }

        if (!this.createMode) {
          this._slugManuallyEdited = true;
          this._metaTitleManuallyEdited = !!this.seo.meta_title;
          this._metaDescManuallyEdited = !!this.seo.meta_description;
        }

        this.$watch("seo.title", (val) => {
          if (this.slugEditable && !this._slugManuallyEdited) {
            this.seo.slug = slugify(val);
          }
          if (!this._metaTitleManuallyEdited) {
            this.seo.meta_title = val || "";
          }
        });

        this.$nextTick(() => {
          this.setupSortable();
          this.initPreview();
        });
      } catch (err) {
        console.error("[PageBuilder] init error:", err);
        window.$toast?.("error", "Initialisation error \u2014 see browser console.");
      }
    },

    // ------------------------------------------------------------------
    // Live preview (server-rendered iframe, Elementor-style)
    // ------------------------------------------------------------------

    get previewFrameStyle() {
      const width =
        this.previewDevice === "mobile" ? "390px"
        : this.previewDevice === "tablet" ? "768px"
        : "100%";
      return `width:${width};max-width:100%;`;
    },

    initPreview() {
      const cfg = window.__PAGE_BUILDER__;
      if (!cfg?.previewUrl || !this.$refs.previewFrame) return;

      window.addEventListener("message", (e) => {
        const frame = this.$refs.previewFrame;
        if (!frame || e.source !== frame.contentWindow) return;
        const d = e.data || {};
        if (d.__pb !== true) return;
        if (d.type === "select" && typeof d.id === "string") {
          this.selectWidget(d.id, { fromPreview: true });
        } else if (d.type === "drop" && typeof d.widgetType === "string") {
          const idx = Number.isInteger(d.index) ? d.index : null;
          this.addWidget(d.widgetType, idx);
        } else if (d.type === "remove" && typeof d.id === "string") {
          // The preview's context menu already ran its own confirm step
          this._removeNow(d.id);
        } else if (d.type === "duplicate" && typeof d.id === "string") {
          this.duplicateWidget(d.id);
        } else if (d.type === "scroll") {
          this._previewScroll = Number(d.y) || 0;
        } else if (d.type === "ready") {
          this._sendToPreview({ type: "state", scroll: this._previewScroll, selectedId: this.selectedId });
        }
      });

      window.addEventListener("beforeunload", (e) => {
        if (!this.dirty) return;
        e.preventDefault();
        e.returnValue = "";
      });

      this.refreshPreview();
    },

    _sendToPreview(msg) {
      const frame = this.$refs.previewFrame;
      try {
        frame?.contentWindow?.postMessage({ __pb: true, ...msg }, "*");
      } catch {
        /* frame not ready yet */
      }
    },

    _markChanged() {
      this.dirty = true;
      this.schedulePreview();
    },

    schedulePreview() {
      clearTimeout(this._previewTimer);
      this._previewTimer = setTimeout(() => this.refreshPreview(), 450);
    },

    async refreshPreview() {
      const cfg = window.__PAGE_BUILDER__;
      const frame = this.$refs.previewFrame;
      if (!cfg?.previewUrl || !frame) return;
      if (this._previewInFlight) {
        this._previewQueued = true;
        return;
      }
      this._previewInFlight = true;
      this.previewLoading = true;
      const token =
        document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
      try {
        const res = await fetch(cfg.previewUrl, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "text/html",
            "X-CSRF-TOKEN": token,
            "X-Requested-With": "XMLHttpRequest",
          },
          body: JSON.stringify({
            version: 1,
            widgets: this.widgets.map((w) => ({ id: w.id, type: w.type, order: w.order, props: w.props })),
          }),
        });
        if (!res.ok) throw new Error("preview " + res.status);
        frame.srcdoc = await res.text();
        this.previewFailed = false;
      } catch {
        if (!this.previewFailed) {
          this.previewFailed = true;
          window.$toast?.("error", "Preview could not refresh \u2014 check your connection, then keep editing; it retries on the next change.");
        }
      } finally {
        this._previewInFlight = false;
        this.previewLoading = false;
        if (this._previewQueued) {
          this._previewQueued = false;
          this.schedulePreview();
        }
      }
    },

    widgetPreview(w) {
      const def = this.definitions.find((d) => d.type === w.type);
      const fields = def?.previewFields || [];
      const p = w.props || {};
      const parts = [];
      for (const key of fields) {
        const t = extractPropText(p[key]);
        if (t) parts.push(t);
      }
      const preview = parts.join(" ").slice(0, 140);
      return preview || (def?.label || w.type);
    },

    selectWidget(id, { fromPreview = false } = {}) {
      if (this.selectedId !== id) {
        this.destroyAllQuill();
      }
      this.selectedId = id;
      if (!fromPreview) {
        this._sendToPreview({ type: "highlight", id });
      }
    },

    onSlugInput() {
      this._slugManuallyEdited = true;
    },

    onMetaTitleInput() {
      this._metaTitleManuallyEdited = true;
    },

    onMetaDescInput() {
      this._metaDescManuallyEdited = true;
    },

    generateMetaDescription() {
      const text = extractTextFromWidgets(this.widgets, this.definitions);
      this.seo.meta_description = text.slice(0, 160);
      this._metaDescManuallyEdited = !!this.seo.meta_description;
    },

    onPaletteDragStart(e, type) {
      // Custom MIME type doubles as the marker the preview iframe checks for
      e.dataTransfer.setData("application/x-pb-widget", type);
      e.dataTransfer.setData("text/plain", type);
      e.dataTransfer.effectAllowed = "copy";
    },

    addWidget(type, index = null) {
      const def = this.definitions.find((d) => d.type === type);
      if (!def) return;
      const instance = {
        id: "w_" + crypto.randomUUID().replace(/-/g, "").slice(0, 10),
        type,
        order: this.widgets.length,
        props: ensureSpacing(deepClone(def.defaultProps)),
      };
      if (index === null || index >= this.widgets.length) {
        this.widgets.push(instance);
      } else {
        this.widgets.splice(index, 0, instance);
      }
      this.reindex();
      this.selectedId = instance.id;
      this._markChanged();
      this.$nextTick(() => this.setupSortable());
    },

    _pendingDelete: {},

    removeWidget(id) {
      if (!this._pendingDelete[id]) {
        this._pendingDelete[id] = true;
        window.$toast?.("warning", "Click delete again to confirm.");
        setTimeout(() => { this._pendingDelete[id] = false; }, 5000);
        return;
      }
      this._pendingDelete[id] = false;
      this._removeNow(id);
    },

    _removeNow(id) {
      const exists = this.widgets.some((w) => w.id === id);
      if (!exists) return;
      this.widgets = this.widgets.filter((w) => w.id !== id);
      if (this.selectedId === id) this.selectedId = null;
      this.reindex();
      this._markChanged();
      this.$nextTick(() => this.setupSortable());
      window.$toast?.("success", "Element removed — save the layout to make it permanent.");
    },

    duplicateWidget(id) {
      const src = this.widgets.find((w) => w.id === id);
      if (!src) return;
      const copy = {
        id: "w_" + crypto.randomUUID().replace(/-/g, "").slice(0, 10),
        type: src.type,
        order: src.order + 1,
        props: deepClone(src.props),
      };
      this.widgets.splice(this.widgets.indexOf(src) + 1, 0, copy);
      this.reindex();
      this.selectedId = copy.id;
      this._markChanged();
      this.$nextTick(() => this.setupSortable());
    },

    reindex() {
      this.widgets.forEach((w, i) => {
        w.order = i;
      });
    },

    reorder(oldIndex, newIndex) {
      if (oldIndex === newIndex || oldIndex < 0 || newIndex < 0) return;
      const arr = this.widgets;
      const [moved] = arr.splice(oldIndex, 1);
      arr.splice(newIndex, 0, moved);
      this.reindex();
      this._markChanged();
    },

    updateProp(key, value) {
      const w = this.selectedWidget;
      if (!w) return;
      w.props[key] = value;
      this._markChanged();
    },

    /*
     * Repeater rows.
     *
     * A repeatable list — the cards in a services grid, the points in a "why choose us" — was
     * declared by the widgets before anything could render it, so the field showed nothing at
     * all and the six services on a themed home page were uneditable. The alternative already
     * available was the `json` field kind, which asks a funeral director to hand-edit an array
     * of objects; that is not an editing surface, it is a dare.
     *
     * Rows are replaced rather than mutated in place. Alpine tracks the array reference, and
     * mutating a nested object leaves the canvas preview showing the previous value until
     * something else happens to trigger a render.
     */
    repeaterRows(field) {
      const value = this.selectedWidget?.props?.[field.name];

      return Array.isArray(value) ? value : [];
    },

    repeaterCanAdd(field) {
      const max = field.max_items || 12;

      return this.repeaterRows(field).length < max;
    },

    repeaterAdd(field) {
      if (!this.repeaterCanAdd(field)) return;

      // A new row carries every key the item schema knows about, so a field a reseller has
      // not filled in yet is an empty string rather than undefined — which would render as
      // the literal word "undefined" in the preview.
      const blank = {};
      (field.item_fields || []).forEach((f) => {
        blank[f.name] = "";
      });

      this.updateProp(field.name, [...this.repeaterRows(field), blank]);
    },

    repeaterRemove(field, index) {
      const rows = this.repeaterRows(field).filter((_, i) => i !== index);
      this.updateProp(field.name, rows);
    },

    repeaterMove(field, index, delta) {
      const rows = [...this.repeaterRows(field)];
      const target = index + delta;

      if (target < 0 || target >= rows.length) return;

      [rows[index], rows[target]] = [rows[target], rows[index]];
      this.updateProp(field.name, rows);
    },

    repeaterSet(field, index, key, value) {
      const rows = this.repeaterRows(field).map((row, i) =>
        i === index ? { ...row, [key]: value } : row
      );

      this.updateProp(field.name, rows);
    },

    /** The line shown on a collapsed row, so a list of six reads as six things. */
    repeaterRowLabel(field, row, index) {
      const first = (field.item_fields || []).find((f) => f.kind === "text" || f.kind === "textarea");
      const text = first ? String(row?.[first.name] ?? "").trim() : "";

      return text !== "" ? text : `${field.item_label || "item"} ${index + 1}`;
    },

    styleVal(key) {
      return this.selectedWidget?.props._style?.[key] ?? "";
    },

    setStyleVal(key, value) {
      const w = this.selectedWidget;
      if (!w) return;
      ensureSpacing(w.props);
      w.props._style[key] = value;
      this._markChanged();
    },

    advVal(key) {
      return this.selectedWidget?.props._advanced?.[key] ?? "";
    },

    setAdvVal(key, value) {
      const w = this.selectedWidget;
      if (!w) return;
      ensureSpacing(w.props);
      w.props._advanced[key] = value;
      this._markChanged();
    },

    spacingVal(group, side) {
      const w = this.selectedWidget;
      if (!w) return "0";
      return w.props._spacing?.[group]?.[side] ?? "0";
    },

    spacingUnit(group) {
      const w = this.selectedWidget;
      if (!w) return "px";
      return w.props._spacing?.[group]?.unit ?? "px";
    },

    spacingLinked(group) {
      const w = this.selectedWidget;
      if (!w) return true;
      return w.props._spacing?.[group]?.linked ?? true;
    },

    setSpacingVal(group, side, val) {
      const w = this.selectedWidget;
      if (!w) return;
      ensureSpacing(w.props);
      const box = w.props._spacing[group];
      val = String(val).replace(/[^0-9.\-]/g, "") || "0";
      if (box.linked) {
        box.top = box.right = box.bottom = box.left = val;
      } else {
        box[side] = val;
      }
      this._markChanged();
    },

    setSpacingUnit(group, unit) {
      const w = this.selectedWidget;
      if (!w) return;
      ensureSpacing(w.props);
      w.props._spacing[group].unit = unit;
      this._markChanged();
    },

    toggleSpacingLinked(group) {
      const w = this.selectedWidget;
      if (!w) return;
      ensureSpacing(w.props);
      const box = w.props._spacing[group];
      box.linked = !box.linked;
      if (box.linked) {
        box.right = box.bottom = box.left = box.top;
      }
      this._markChanged();
    },

    /*
     * Repeater rows.
     *
     * A list of things — six services, three reasons to choose us — was previously a raw JSON
     * textarea. That is a fine escape hatch for a developer and a wall for the funeral
     * director this builder is actually for, which made every list-shaped widget effectively
     * uneditable by its owner.
     *
     * Rows are plain objects shaped by the widget's item schema. Everything below mutates
     * w.props[key] in place and marks the document changed, so undo/redo and the live preview
     * behave exactly as they do for a scalar field.
     */
    repeaterRows(key) {
      const w = this.selectedWidget;
      const v = w?.props?.[key];
      return Array.isArray(v) ? v : [];
    },

    repeaterAdd(field) {
      const w = this.selectedWidget;
      if (!w) return;
      if (!Array.isArray(w.props[field.name])) w.props[field.name] = [];

      const max = field.max_items || 0;
      if (max && w.props[field.name].length >= max) return;

      // Seeded from the item schema so a new row has every key the renderer reads, rather
      // than an empty object that renders as a gap until each field is touched.
      const row = {};
      (field.item_fields || []).forEach((f) => {
        row[f.name] = f.default !== undefined ? f.default : "";
      });

      w.props[field.name].push(row);
      this._markChanged();
    },

    repeaterRemove(key, index) {
      const w = this.selectedWidget;
      if (!w || !Array.isArray(w.props[key])) return;
      w.props[key].splice(index, 1);
      this._markChanged();
    },

    repeaterMove(key, index, delta) {
      const w = this.selectedWidget;
      if (!w || !Array.isArray(w.props[key])) return;
      const rows = w.props[key];
      const target = index + delta;
      if (target < 0 || target >= rows.length) return;
      [rows[index], rows[target]] = [rows[target], rows[index]];
      this._markChanged();
    },

    repeaterSet(key, index, name, value) {
      const w = this.selectedWidget;
      if (!w || !Array.isArray(w.props[key])) return;
      if (!w.props[key][index]) return;
      w.props[key][index][name] = value;
      this._markChanged();
    },

    repeaterVal(key, index, name) {
      const rows = this.repeaterRows(key);
      return rows[index]?.[name] ?? "";
    },

    /** The row's own label on the collapsed header, so a list of six is navigable. */
    repeaterRowLabel(field, index) {
      const rows = this.repeaterRows(field.name);
      const row = rows[index] || {};
      for (const f of field.item_fields || []) {
        const v = row[f.name];
        if (typeof v === "string" && v.trim() !== "") {
          return v.trim().slice(0, 40);
        }
      }
      return (field.item_label || "Item") + " " + (index + 1);
    },

    jsonProp(key) {
      const w = this.selectedWidget;
      if (!w) return "";
      const v = w.props[key];
      if (v === undefined || v === null) return "";
      return typeof v === "string" ? v : JSON.stringify(v, null, 2);
    },

    setJsonProp(key, text) {
      const w = this.selectedWidget;
      if (!w) return;
      const errKey = w.id + "-" + key;
      try {
        w.props[key] = JSON.parse(text || "null");
        delete this.jsonErrors[errKey];
        this._markChanged();
      } catch {
        this.jsonErrors[errKey] = 'Fix the JSON syntax to apply this change — e.g. ["First item", "Second item"]';
      }
    },

    jsonError(key) {
      const w = this.selectedWidget;
      if (!w) return "";
      return this.jsonErrors[w.id + "-" + key] || "";
    },

    _quillInstances: {},

    _quillToolbar: [
      // h1 is reserved for the page title / Heading widget
      [{ header: [2, 3, 4, false] }],
      [{ size: ["small", false, "large", "huge"] }],
      ["bold", "italic", "underline"],
      [{ color: [] }],
      ["link", "blockquote"],
      [{ list: "ordered" }, { list: "bullet" }],
      [{ align: [] }],
      ["clean"],
      ["code-block"],
    ],

    destroyAllQuill() {
      Object.keys(this._quillInstances).forEach((k) => {
        delete this._quillInstances[k];
      });
    },

    initQuill(widget, fieldName) {
      if (typeof Quill === "undefined" || !widget) return;
      const key = widget.id + "-" + fieldName;
      if (this._quillInstances[key]) return;

      this.destroyAllQuill();

      const el = document.getElementById("pb-quill-" + key);
      if (!el) return;
      el.innerHTML = "";

      const q = new Quill(el, {
        theme: "snow",
        placeholder: "Write your content\u2026",
        modules: { toolbar: this._quillToolbar },
      });

      const content = widget.props[fieldName] || "";
      if (content.trim()) {
        q.clipboard.dangerouslyPasteHTML(0, content);
      }

      q.on("text-change", () => {
        const html = q.root.innerHTML?.trim() ?? "";
        const cleaned = html === "<p><br></p>" || !html ? "" : html;
        this.updateProp(fieldName, cleaned);
      });

      this._quillInstances[key] = q;
    },

    destroyQuillOnDeselect(widget, fieldName) {
      if (!widget) this.destroyAllQuill();
    },

    setupSortable() {
      const canvas = this.$refs.widgetCanvas;
      if (!canvas) return;

      if (this._canvasSortable) this._canvasSortable.destroy();

      this._canvasSortable = Sortable.create(canvas, {
        animation: 150,
        draggable: "[data-pb-item]",
        onEnd: (evt) => {
          if (evt.oldIndex === evt.newIndex) return;

          // Sortable already moved the DOM element. We must undo that move
          // and let Alpine's reactivity re-render the list in the right order.
          const { item, from, oldIndex, newIndex } = evt;
          from.removeChild(item);
          const ref = from.querySelectorAll("[data-pb-item]")[oldIndex];
          if (ref) {
            from.insertBefore(item, ref);
          } else {
            from.appendChild(item);
          }

          this.reorder(oldIndex, newIndex);
        },
      });
    },

    formatApiErrors(data) {
      if (!data?.errors || typeof data.errors !== "object") return "";
      return Object.values(data.errors)
        .flat()
        .filter(Boolean)
        .join(" ");
    },

    async save() {
      const cfg = window.__PAGE_BUILDER__;
      if (!cfg) return;
      this.saving = true;
      const token =
        document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";

      if (this.createMode && cfg.storeUrl) {
        const slug = String(this.seo.slug || "")
          .trim()
          .toLowerCase();
        if (!slug || !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug)) {
          window.$toast?.("warning", "Enter a valid URL slug (lowercase letters, numbers, hyphens only).");
          this.saving = false;
          return;
        }
        const title = String(this.seo.title || "").trim();
        if (!title) {
          window.$toast?.("warning", "Enter a page title under Page details.");
          this.saving = false;
          return;
        }
        try {
          const body = {
            slug,
            title,
            meta_title: this.seo.meta_title || null,
            meta_description: this.seo.meta_description || null,
            is_published: this.seo.is_published ? true : false,
            layout: {
              version: 1,
              widgets: this.widgets.map((w) => ({
                id: w.id,
                type: w.type,
                order: w.order,
                props: w.props,
              })),
            },
          };
          const res = await fetch(cfg.storeUrl, {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              Accept: "application/json",
              "X-CSRF-TOKEN": token,
              "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify(body),
          });
          const data = await res.json().catch(() => ({}));
          if (!res.ok) {
            window.$toast?.("error", this.formatApiErrors(data) || data.message || "Could not create page.");
            return;
          }
          this.dirty = false;
          if (data.redirect) {
            window.$toast?.("success", data.message || "Page created!");
            window.location.assign(data.redirect);
            return;
          }
          window.$toast?.("success", data.message || "Page created!");
        } catch {
          window.$toast?.("error", "Network error.");
        } finally {
          this.saving = false;
        }
        return;
      }

      if (!cfg.saveUrl) {
        this.saving = false;
        window.$toast?.("error", "Save is unavailable — reload this page and try again.");
        return;
      }

      if (this.slugEditable) {
        const cur = String(this.seo.slug || "")
          .trim()
          .toLowerCase();
        const orig = String(this.originalSlug || "")
          .trim()
          .toLowerCase();
        if (cur !== orig) {
          window.$toast?.("warning", "Save page details first \u2014 the URL slug was changed.");
          this.saving = false;
          return;
        }
      }

      try {
        const body = {
          version: 1,
          widgets: this.widgets.map((w) => ({
            id: w.id,
            type: w.type,
            order: w.order,
            props: w.props,
          })),
        };
        const res = await fetch(cfg.saveUrl, {
          method: "PUT",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-CSRF-TOKEN": token,
            "X-Requested-With": "XMLHttpRequest",
          },
          body: JSON.stringify(body),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
          window.$toast?.("error", this.formatApiErrors(data) || data.message || "Save failed.");
          return;
        }
        this.dirty = false;
        window.$toast?.("success", data.message || "Layout saved.");
      } catch {
        window.$toast?.("error", "Network error.");
      } finally {
        this.saving = false;
      }
    },

    async saveSeo() {
      const cfg = window.__PAGE_BUILDER__;
      if (this.createMode) {
        window.$toast?.("info", "Use Create page in the toolbar \u2014 URL, title, and meta are saved with the new page.");
        return;
      }
      if (!cfg?.seoUrl) return;
      this.seoSaving = true;
      const token =
        document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
      try {
        const fd = new FormData();
        if (this.slugEditable) {
          fd.append(
            "slug",
            String(this.seo.slug || "")
              .trim()
              .toLowerCase()
          );
        }
        fd.append("title", this.seo.title || "");
        fd.append("meta_title", this.seo.meta_title || "");
        fd.append("meta_description", this.seo.meta_description || "");
        fd.append("is_published", this.seo.is_published ? "1" : "0");
        fd.append("remove_og_image", this.seo.remove_og_image ? "1" : "0");
        const fileInput = this.$refs.seoOgFile;
        if (fileInput?.files?.[0]) {
          fd.append("og_image", fileInput.files[0]);
        }
        const res = await fetch(cfg.seoUrl, {
          method: "POST",
          headers: {
            Accept: "application/json",
            "X-CSRF-TOKEN": token,
            "X-Requested-With": "XMLHttpRequest",
          },
          body: fd,
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
          window.$toast?.("error", data.message || (data.errors && Object.values(data.errors).flat().join(" ")) || "Save failed.");
          return;
        }
        window.$toast?.("success", data.message || "Page details saved.");
        this.seo.remove_og_image = false;
        if (fileInput) fileInput.value = "";
        if (data.og_image_url !== undefined) {
          this.seo.og_image_url = data.og_image_url || null;
        }
        if (data.title) {
          this.seo.title = data.title;
        }
        if (data.slug_changed && data.redirect) {
          window.location.assign(data.redirect);
          return;
        }
        if (data.new_slug) {
          this.seo.slug = data.new_slug;
          this.originalSlug = data.new_slug;
        }
      } catch {
        window.$toast?.("error", "Network error.");
      } finally {
        this.seoSaving = false;
      }
    },
  }));
});
