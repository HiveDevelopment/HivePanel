"use strict";
(() => {
  // ../../dist/index.js
  var NectarError = class extends Error {
    code;
    status;
    constructor(message, code, status2) {
      super(message);
      this.code = code;
      this.status = status2;
      this.name = "NectarError";
    }
  };
  function createNectar(options = {}) {
    const host = window;
    if (host.parent === host)
      throw new NectarError("Open this extension inside HivePanel.", "NOT_EMBEDDED");
    const origin = new URL(host.location.href).origin;
    const timeout = options.timeoutMs ?? 15e3;
    const handshakeTimeout = options.handshakeTimeoutMs ?? 1e4;
    if (![timeout, handshakeTimeout].every((value) => Number.isFinite(value) && value > 0))
      throw new TypeError("Timeouts must be positive.");
    let nonce;
    let context2;
    let disposed = false;
    let sequence = 0;
    const requestPrefix = Array.from(crypto.getRandomValues(new Uint8Array(8)), (value) => value.toString(16).padStart(2, "0")).join("");
    let readySettled = false;
    const pending = /* @__PURE__ */ new Map();
    const uiListeners = /* @__PURE__ */ new Set();
    const eventListeners = /* @__PURE__ */ new Set();
    let resolveReady;
    let rejectReady;
    const ready = new Promise((resolve, reject) => {
      resolveReady = resolve;
      rejectReady = reject;
    });
    void ready.catch(() => {
    });
    const send = (payload) => host.parent.postMessage({ nectar: "v1", ...payload }, "*");
    function notify(listeners, value) {
      for (const listener of listeners) {
        try {
          listener(value);
        } catch (error) {
          console.error("Nectar listener failed", error);
        }
      }
    }
    function validUI(value) {
      const ui = value;
      return !!ui && typeof ui.locale === "string" && !!ui.theme && typeof ui.theme === "object" && !!ui.messages && typeof ui.messages === "object";
    }
    function receive(event) {
      if (disposed || event.source !== host.parent || event.origin !== origin)
        return;
      const message = event.data;
      if (!message || message.nectar !== "v1")
        return;
      if (message.type === "init" && !readySettled && typeof message.nonce === "string" && /^[a-f0-9]{48}$/.test(message.nonce) && validUI(message.ui) && (message.cellId === null || typeof message.cellId === "string")) {
        nonce = message.nonce;
        context2 = { cellId: message.cellId, ui: message.ui };
        readySettled = true;
        clearInterval(retry);
        clearTimeout(handshakeTimer);
        resolveReady(context2);
        return;
      }
      if (!nonce || message.nonce !== nonce)
        return;
      if (message.type === "ui" && validUI(message.ui) && context2) {
        context2 = { ...context2, ui: message.ui };
        notify(uiListeners, message.ui);
      } else if (message.type === "event" && ["cell.created", "cell.updated", "cell.deleted"].includes(message.event) && message.cell && typeof message.cell.id === "string") {
        notify(eventListeners, { event: message.event, cell: message.cell });
      } else if (message.type === "response" && typeof message.requestId === "string") {
        const item = pending.get(message.requestId);
        if (!item)
          return;
        clearTimeout(item.timer);
        pending.delete(message.requestId);
        if (message.ok === true)
          item.resolve(message.data);
        else
          item.reject(new NectarError(typeof message.error === "string" ? message.error : "Capability request failed.", "CAPABILITY_FAILED", typeof message.status === "number" ? message.status : void 0));
      }
    }
    host.addEventListener("message", receive);
    const retry = setInterval(() => send({ type: "ready" }), 250);
    const handshakeTimer = setTimeout(() => {
      readySettled = true;
      clearInterval(retry);
      host.removeEventListener("message", receive);
      rejectReady(new NectarError("Nectar handshake timed out. Open the extension in a compatible Panel.", "HANDSHAKE_TIMEOUT"));
    }, handshakeTimeout);
    send({ type: "ready" });
    async function request(method, params = {}) {
      if (disposed)
        throw new NectarError("Nectar client was disposed.", "DISPOSED");
      await ready;
      if (disposed)
        throw new NectarError("Nectar client was disposed.", "DISPOSED");
      if (pending.size >= 10)
        throw new NectarError("Wait for an existing request to finish.", "TOO_MANY_REQUESTS");
      const requestId = `sdk-${requestPrefix}-${++sequence}`;
      return new Promise((resolve, reject) => {
        const timer = setTimeout(() => {
          pending.delete(requestId);
          reject(new NectarError("Nectar request timed out.", "REQUEST_TIMEOUT"));
        }, timeout);
        pending.set(requestId, { resolve: (data) => resolve(data), reject, timer });
        try {
          send({ type: "request", nonce, requestId, method, params });
        } catch (error) {
          clearTimeout(timer);
          pending.delete(requestId);
          reject(error);
        }
      });
    }
    return {
      ready,
      get context() {
        return context2;
      },
      cells: { list: () => request("cells.list").then((result) => result.cells) },
      settings: { get: () => request("settings.get").then((result) => result.settings) },
      network: {
        http: (params) => request("network.http", params),
        tcp: (params) => request("network.tcp", { ...params })
      },
      events: {
        // Replaces the event set for this frame. An empty set stops polling.
        subscribe: (events) => request("events.subscribe", { events }),
        on: (listener) => {
          eventListeners.add(listener);
          return () => {
            eventListeners.delete(listener);
          };
        }
      },
      onUI: (listener) => {
        uiListeners.add(listener);
        return () => {
          uiListeners.delete(listener);
        };
      },
      dispose() {
        if (disposed)
          return;
        if (nonce) {
          try {
            send({ type: "request", nonce, requestId: `sdk-${requestPrefix}-${++sequence}`, method: "events.subscribe", params: { events: [] } });
          } catch {
          }
        }
        disposed = true;
        clearInterval(retry);
        clearTimeout(handshakeTimer);
        host.removeEventListener("message", receive);
        if (!readySettled) {
          readySettled = true;
          rejectReady(new NectarError("Nectar client was disposed.", "DISPOSED"));
        }
        for (const item of pending.values()) {
          clearTimeout(item.timer);
          item.reject(new NectarError("Nectar client was disposed.", "DISPOSED"));
        }
        pending.clear();
        uiListeners.clear();
        eventListeners.clear();
      }
    };
  }
  function applyTheme(ui, root = document.documentElement) {
    for (const [name, value] of Object.entries(ui.theme)) {
      if (/^--hp-[a-zA-Z]+$/.test(name) && typeof value === "string")
        root.style.setProperty(name, value);
    }
    root.lang = ui.locale;
  }

  // src/main.ts
  var app = document.querySelector("#app");
  var nectar = createNectar();
  var title = document.createElement("h1");
  var context = document.createElement("p");
  var status = document.createElement("p");
  var refresh = document.createElement("button");
  var list = document.createElement("ul");
  refresh.textContent = "Refresh Cells";
  app.replaceChildren(title, context, refresh, status, list);
  async function load() {
    refresh.disabled = true;
    status.textContent = "Loading\u2026";
    try {
      const cells = await nectar.cells.list();
      list.replaceChildren(...cells.map((cell) => {
        const item = document.createElement("li");
        item.textContent = `${cell.name} \xB7 ${cell.installStatus}`;
        return item;
      }));
      status.textContent = cells.length ? `${cells.length} visible Cell(s)` : "No Cells are available to your account.";
    } catch (error) {
      status.textContent = error instanceof Error ? error.message : "Could not load Cells.";
    } finally {
      refresh.disabled = false;
    }
  }
  async function start() {
    try {
      const initial = await nectar.ready;
      applyTheme(initial.ui);
      nectar.onUI((ui) => {
        applyTheme(ui);
        context.textContent = `Language: ${ui.locale}`;
      });
      context.textContent = initial.cellId ? `Cell page: ${initial.cellId}` : `Language: ${initial.ui.locale}`;
      const settings = await nectar.settings.get();
      title.textContent = settings.greeting || "Hello Nectar";
      await load();
      nectar.events.on(() => {
        void load();
      });
      await nectar.events.subscribe(["cell.created", "cell.updated", "cell.deleted"]);
    } catch (error) {
      status.textContent = error instanceof Error ? error.message : "Could not initialize.";
    }
  }
  refresh.addEventListener("click", () => void load());
  window.addEventListener("pagehide", () => nectar.dispose(), { once: true });
  void start();
})();
