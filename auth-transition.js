(() => {
  const page = document.querySelector(".page");
  if (!page) return;

  // fade-in po načtení
  requestAnimationFrame(() => page.classList.add("is-ready"));

  // fade-out při přechodu klikem na odkaz (jen interní)
  document.addEventListener("click", (e) => {
    const a = e.target.closest("a.js-nav");
    if (!a) return;

    // new tab / ctrl / middle click necháme být
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button === 1) return;

    const href = a.getAttribute("href");
    if (!href || href.startsWith("http") || href.startsWith("#")) return;

    e.preventDefault();
    page.classList.remove("is-ready");
    page.classList.add("is-leaving");

    window.setTimeout(() => {
      window.location.href = href;
    }, 220);
  });
})();
