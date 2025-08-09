// FastFood Pro - Settings UI helpers (zones + validation)
jQuery(function ($) {
  const optName = "ffp_settings";
  const $form = $('form[action="options.php"]').first();
  const $zonesTable = $(".ffp-zones-table");
  const $tbody = $zonesTable.find("tbody");
  const addBtnSel = "#ffp-add-zone";

  function escapeHtml(s) {
    return String(s == null ? "" : s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }
  function escapeAttr(s) {
    return s == null ? "" : String(s);
  }

  function zoneRowTpl(values) {
    const v = Object.assign(
      { name: "", regex: "", base: "", per_km: "", min: "", max: "" },
      values || {}
    );
    return `
      <tr class="ffp-zone-row">
        <td><input type="text" name="${optName}[zone_name][]" value="${escapeHtml(v.name)}" placeholder="Sentrum"></td>
        <td>
          <div class="ffp-zone-regex">
            <input type="text" name="${optName}[zone_regex][]" value="${escapeHtml(v.regex)}" placeholder="^40(\\d{2})$">
            <input type="text" class="ffp-regex-test" placeholder="Test: 4020" title="Skriv postnummer for å teste regex">
            <span class="ffp-test-result" aria-live="polite"></span>
          </div>
        </td>
        <td><input type="number" step="0.01" min="0" name="${optName}[zone_base][]" value="${escapeAttr(v.base)}" placeholder="29"></td>
        <td><input type="number" step="0.01" min="0" name="${optName}[zone_per_km][]" value="${escapeAttr(v.per_km)}" placeholder="6"></td>
        <td><input type="number" step="0.01" min="0" name="${optName}[zone_min][]" value="${escapeAttr(v.min)}" placeholder="39"></td>
        <td><input type="number" step="0.01" min="0" name="${optName}[zone_max][]" value="${escapeAttr(v.max)}" placeholder="149"></td>
        <td class="ffp-zone-actions">
          <button type="button" class="button ffp-move-up" title="Flytt opp">↑</button>
          <button type="button" class="button ffp-move-down" title="Flytt ned">↓</button>
          <button type="button" class="button ffp-dup-zone" title="Dupliser">⎘</button>
          <button type="button" class="button ffp-remove-zone" title="Fjern">✕</button>
        </td>
      </tr>
    `;
  }

  function readRow($row) {
    return {
      name: $row.find(`input[name="${optName}[zone_name][]"]`).val()?.trim() || "",
      regex: $row.find(`input[name="${optName}[zone_regex][]"]`).val()?.trim() || "",
      base: $row.find(`input[name="${optName}[zone_base][]"]`).val(),
      per_km: $row.find(`input[name="${optName}[zone_per_km][]"]`).val(),
      min: $row.find(`input[name="${optName}[zone_min][]"]`).val(),
      max: $row.find(`input[name="${optName}[zone_max][]"]`).val(),
    };
  }

  function focusLastRowFirstInput() {
    const $last = $tbody.find("tr").last();
    $last.find(`input[name="${optName}[zone_name][]"]`).trigger("focus");
  }

  function labelOf(key) {
    switch (key) {
      case "base": return "Base";
      case "per_km": return "Pris/km";
      case "min": return "Min";
      case "max": return "Maks";
      default: return key;
    }
  }

  // Legg til ny sone
  $(document).on("click", addBtnSel, function (e) {
    e.preventDefault();
    $tbody.append(zoneRowTpl());
    focusLastRowFirstInput();
  });

  // Fjern sone
  $(document).on("click", ".ffp-remove-zone", function (e) {
    e.preventDefault();
    $(this).closest("tr").remove();
  });

  // Dupliser sone
  $(document).on("click", ".ffp-dup-zone", function (e) {
    e.preventDefault();
    const $row = $(this).closest("tr");
    const vals = readRow($row);
    $row.after(zoneRowTpl(vals));
  });

  // Flytt opp/ned
  $(document).on("click", ".ffp-move-up", function (e) {
    e.preventDefault();
    const $row = $(this).closest("tr");
    const $prev = $row.prev("tr");
    if ($prev.length) $row.insertBefore($prev);
  });
  $(document).on("click", ".ffp-move-down", function (e) {
    e.preventDefault();
    const $row = $(this).closest("tr");
    const $next = $row.next("tr");
    if ($next.length) $row.insertAfter($next);
  });

  // Enter i siste felt (Maks) => legg til ny rad
  $(document).on("keydown", `.ffp-zone-row input[name^="${optName}[zone_max]"]`, function (e) {
    if (e.key === "Enter") {
      e.preventDefault();
      $(addBtnSel).trigger("click");
    }
  });

  // Live regex test
  $(document).on("input", `.ffp-zone-regex .ffp-regex-test, .ffp-zone-regex input[name^='${optName}[zone_regex]']`, function () {
    const $wrap = $(this).closest(".ffp-zone-regex");
    const pattern = $wrap.find(`input[name^='${optName}[zone_regex]']`).val().trim();
    const probe = $wrap.find(".ffp-regex-test").val().trim();
    const $out = $wrap.find(".ffp-test-result");

    if (!pattern || !probe) {
      $out.text("");
      return;
    }
    try {
      const re = new RegExp(pattern);
      const ok = re.test(probe);
      $out.text(ok ? "✔" : "✖").css("color", ok ? "#008a00" : "#cc0000");
    } catch (err) {
      $out.text("!").css("color", "#cc0000").attr("title", "Ugyldig regex");
    }
  });

  // Trim input på blur
  $(document).on("blur", ".ffp-zones-table input[type='text']", function () {
    this.value = this.value.trim();
  });

  // Validering før lagring
  $form.on("submit", function (e) {
    const errors = [];
    let rowIndex = 0;

    $tbody.find("tr").each(function () {
      rowIndex++;
      const v = readRow($(this));

      if (!v.name) errors.push(`Rad ${rowIndex}: Navn kan ikke være tomt.`);

      ["base", "per_km", "min", "max"].forEach((k) => {
        if (v[k] === "" || isNaN(v[k])) {
          errors.push(`Rad ${rowIndex}: Feltet ${labelOf(k)} må være et tall.`);
        } else if (Number(v[k]) < 0) {
          errors.push(`Rad ${rowIndex}: Feltet ${labelOf(k)} kan ikke være negativt.`);
        }
      });

      if (v.min !== "" && v.max !== "" && Number(v.min) > Number(v.max)) {
        errors.push(`Rad ${rowIndex}: Min kan ikke være større enn Maks.`);
      }

      if (v.regex) {
        try { new RegExp(v.regex); }
        catch { errors.push(`Rad ${rowIndex}: Ugyldig regex.`); }
      }
    });

    if (errors.length) {
      e.preventDefault();
      alert("Kan ikke lagre:\n\n" + errors.join("\n"));
    }
  });
});
