(function () {
  var root = document.body.getAttribute("data-app-root") || "";
  var prefix = root === "" ? "" : root.replace(/\/?$/, "/");
  var swPath = prefix + "sw.js";
  var scope = prefix === "" ? "./" : prefix;

  if ("serviceWorker" in navigator) {
    navigator.serviceWorker.register(swPath, { scope: scope }).catch(function () {});
  }
})();
