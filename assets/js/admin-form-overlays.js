/**
 * Suites admin form presentation standard.
 * Existing forms, CSRF values, handlers and field names are moved, not recreated.
 * Excludes filters, inline actions, already-modalized forms and voice controls.
 */
(() => {
  "use strict";
  if (!document.body.classList.contains("admin-mode")) return;
  const root = document.querySelector("main.admin-main");
  if (!root) return;
  const page = new URLSearchParams(location.search).get("p")
    || location.pathname.split("/").pop().replace(/\.php$/, "");
  const skipPages = new Set(["login","dashboard","map","reservation_new","reservation_edit","room_manage","rooms","messages","integrations","map_settings","guests","products_services","attractions","gallery","gallery_media_add","gallery_media_update","block_edit","ai_test","user_edit","financeiro","whatsapp_rnl","whatsapp_rnl_legacy","crm360","crm_contacts","crm_leads","crm_tasks"]);
  if (skipPages.has(page)) return;

  const labels = {
    ai: {save:"Configurar IA",kb_add:"Adicionar à base",test:"Testar IA"},
    users: {default:"Adicionar usuário"},
    content: {default:"Adicionar conteúdo"},
    reviews: {default:"Editar avaliação"},
    property: {default:"Editar estabelecimento"},
    site_admin: {default:"Configurar site"},
    promotions: {default:"Adicionar promoção"},
    contacts_admin: {default:"Editar contato"},
    payments: {default:"Editar pagamento"},
    livro_caixa: {default:"Adicionar lançamento"},
    attendance_settings: {default:"Configurar atendimento"},
    reports: {default:"Configurar relatório"}
  };
  const isWithinExistingModal = (el) => Boolean(el.closest('dialog,[role="dialog"],.modal,.drawer,.overlay,.ig-dialog,.ms-modal,.guest-modal,.catalog-modal,.consumption-modal,.attractions-dialog'));
  const eligible = (form) => {
    if (isWithinExistingModal(form) || form.closest("table,.table-scroll,.inbox-sidebar,.inbox-detail,.map-grid,.map-toolbar,.quick-actions,.guest-actions,.catalog-actions")) return false;
    if (form.hasAttribute("data-no-auto-modal") || form.closest("[data-no-auto-modal]")) return false;
    if ((form.getAttribute("method") || "get").toLowerCase() !== "post") return false;
    const fields = [...form.querySelectorAll("input:not([type=hidden]):not([type=submit]):not([type=button]),textarea,select")];
    // The standalone AI test is one textarea; a card form itself denotes an editor.
    return fields.length >= 2 || (form.classList.contains("card") && fields.length >= 1);
  };
  const forms = [...root.querySelectorAll("form")].filter(eligible);
  if (!forms.length) return;

  let index = 0;
  let autoOpen = null;
  const headingFallback = labels[page]?.default || "Configurar";
  const sectionButtons = new Map();
  const buttons = [];
  const aiActions = page === "ai" ? document.createElement("div") : null;
  if (aiActions) { aiActions.className = "admin-form-action-grid"; root.prepend(aiActions); }

  for (const form of forms) {
    index++;
    const type = form.querySelector('input[name="action"]')?.value || "";
    const heading = form.querySelector("h2,h3")?.textContent.trim();
    const submit = form.querySelector('button[type="submit"],button:not([type])')?.textContent.trim();
    let name = labels[page]?.[type] || heading || headingFallback;
    if (name.toLowerCase().includes("base de conhecimento") && type === "kb_add") name = "Adicionar à base";
    if (!heading && !labels[page]?.[type] && submit && !name) name = submit;
    const title = name.replace(/\s+/g, " ").slice(0,100);
    const dialog = document.createElement("dialog");
    dialog.className = "admin-form-standard-dialog";
    dialog.id = "admin-form-overlay-" + index;
    dialog.setAttribute("aria-label", title);
    const top = document.createElement("header");
    top.className = "admin-form-standard-head";
    const h = document.createElement("h2");h.textContent = title;
    const close = document.createElement("button");close.type = "button";close.className = "admin-form-standard-close";close.setAttribute("aria-label","Fechar");close.textContent = "×";
    top.append(h,close);
    const body = document.createElement("div");body.className = "admin-form-standard-body";
    const trigger = document.createElement("button");trigger.type = "button";trigger.className = "btn admin-form-standard-trigger";trigger.setAttribute("aria-haspopup","dialog");trigger.setAttribute("aria-controls",dialog.id);
    const svg = document.createElementNS("http://www.w3.org/2000/svg","svg");svg.setAttribute("viewBox","0 0 24 24");svg.setAttribute("width","18");svg.setAttribute("height","18");svg.setAttribute("aria-hidden","true");svg.setAttribute("fill","none");svg.setAttribute("stroke","currentColor");svg.setAttribute("stroke-width","2");
    const path = document.createElementNS("http://www.w3.org/2000/svg","path");path.setAttribute("d",type === "kb_add" ? "M12 5v14M5 12h14" : "M12 20h9M16.5 3.5l4 4L10 18l-5 1 1-5z");
    svg.append(path);trigger.append(svg,document.createTextNode(title));

    const oldParent = form.parentElement;
    // A two-column listing + form becomes a full-width listing after the form moves.
    if (oldParent?.classList.contains("split")) oldParent.classList.add("admin-modalized-split");
    const wrapper = document.createElement("div");wrapper.className = "admin-form-standard-action";
    form.before(wrapper);
    wrapper.append(trigger);
    if (aiActions) {
      aiActions.append(wrapper);
    }
    // Place the trigger in the section toolbar where possible.
    // Do not place buttons inside table rows or duplicate nested forms.
    if (!aiActions && oldParent?.classList.contains("split") && oldParent.parentElement === root) {
      root.insertBefore(wrapper, oldParent);
    } else if (!aiActions && form.classList.contains("card") && oldParent === root) {
      root.insertBefore(wrapper, form);
    }
    body.append(form);
    dialog.append(top,body);
    root.append(dialog);
    form.classList.add("admin-form-standard-moved");
    close.addEventListener("click",()=>dialog.close());
    dialog.addEventListener("click",event=>{if (event.target===dialog) dialog.close();});
    trigger.addEventListener("click",()=>{dialog.showModal();dialog.querySelector("input:not([type=hidden]),select,textarea")?.focus({preventScroll:true});});
    buttons.push(trigger);
    const idField=form.querySelector('input[name="id"][type="hidden"]');
    if ((page === "content" || page === "users") && idField?.value && new URLSearchParams(location.search).has("id")) autoOpen = dialog;
  }
  if (autoOpen) autoOpen.showModal();
})();
