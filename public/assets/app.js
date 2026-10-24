(() => {
  const menuButton = document.querySelector(".nav-toggle");
  const navigation = document.querySelector("#site-nav");

  if (menuButton && navigation) {
    menuButton.addEventListener("click", () => {
      const expanded = menuButton.getAttribute("aria-expanded") === "true";
      menuButton.setAttribute("aria-expanded", String(!expanded));
      navigation.classList.toggle("nav-open", !expanded);
    });
  }

  document.querySelectorAll("[data-copy]").forEach((button) => {
    button.addEventListener("click", async () => {
      const value = button.getAttribute("data-copy") || "";
      try {
        await navigator.clipboard.writeText(value);
        button.textContent = "Copied";
      } catch {
        const input = document.createElement("textarea");
        input.value = value;
        input.style.position = "fixed";
        input.style.opacity = "0";
        document.body.append(input);
        input.select();
        document.execCommand("copy");
        input.remove();
        button.textContent = "Copied";
      }
      window.setTimeout(() => {
        button.textContent = "Copy ID";
      }, 1600);
    });
  });

  const reportForm = document.querySelector("[data-report-form]");
  if (reportForm) {
    const category = reportForm.querySelector("[data-category]");
    const location = reportForm.querySelector('[name="location_address"]');
    const panel = reportForm.querySelector("[data-match-panel]");
    let timer;
    let abortController;

    const checkMatches = async () => {
      const categoryValue = category?.value || "";
      const locationValue = location?.value.trim() || "";
      if (!categoryValue || locationValue.length < 4 || !panel) {
        if (panel) {
          panel.hidden = true;
          panel.replaceChildren();
        }
        return;
      }
      abortController?.abort();
      abortController = new AbortController();
      const endpoint = new URL("index.php", window.location.href);
      endpoint.searchParams.set("page", "matches");
      endpoint.searchParams.set("category", categoryValue);
      endpoint.searchParams.set("location", locationValue);

      try {
        const response = await fetch(endpoint, {
          headers: { Accept: "application/json" },
          signal: abortController.signal,
        });
        if (!response.ok) throw new Error("Could not check reports");
        const matches = await response.json();
        panel.replaceChildren();
        const heading = document.createElement("strong");
        heading.textContent = matches.length
          ? "Similar active reports nearby"
          : "No matching active reports found";
        panel.append(heading);

        if (matches.length) {
          const list = document.createElement("ul");
          matches.forEach((match) => {
            const item = document.createElement("li");
            const summary = document.createElement("span");
            summary.textContent = `${match.title} — ${match.location_address}`;
            const link = document.createElement("a");
            const trackUrl = new URL("index.php", window.location.href);
            trackUrl.searchParams.set("page", "track");
            trackUrl.searchParams.set("id", match.complaint_id);
            link.href = trackUrl.toString();
            link.textContent = `Support report ${match.complaint_id}`;
            item.append(summary, link);
            list.append(item);
          });
          panel.append(list);
          const hint = document.createElement("span");
          hint.className = "match-hint";
          hint.textContent =
            "If one describes the same issue, open it and add your support instead of creating a duplicate.";
          panel.append(hint);
        } else {
          const hint = document.createElement("span");
          hint.className = "match-hint";
          hint.textContent =
            "Please check the location carefully before submitting a new report.";
          panel.append(hint);
        }
        panel.hidden = false;
      } catch (error) {
        if (error.name !== "AbortError") {
          panel.replaceChildren();
          const hint = document.createElement("span");
          hint.className = "match-hint";
          hint.textContent =
            "Similar reports could not be checked just now. You can still submit; the server will check for an exact duplicate.";
          panel.append(hint);
          panel.hidden = false;
        }
      }
    };

    const scheduleCheck = () => {
      window.clearTimeout(timer);
      timer = window.setTimeout(checkMatches, 350);
    };
    category?.addEventListener("change", scheduleCheck);
    location?.addEventListener("input", scheduleCheck);
    category?.addEventListener("change", checkMatches);
    if (category?.value && location?.value) checkMatches();
  }

  const tracker = document.querySelector("[data-track-id][data-track-url]");
  if (tracker) {
    const endpoint = new URL(tracker.dataset.trackUrl, window.location.href);
    endpoint.searchParams.set("id", tracker.dataset.trackId);
    const statusLabels = tracker.querySelectorAll("[data-live-status]");
    const timeline = tracker.querySelector("[data-timeline]");
    const updated = tracker.querySelector("[data-last-updated]");
    let previousSignature = "";

    const statusClass = (status) => {
      if (status === "Submitted") return "status-submitted";
      if (status === "Under Verification") return "status-review";
      if (status === "Work Assigned") return "status-assigned";
      if (status === "In Progress") return "status-progress";
      if (["Resolved", "Closed"].includes(status)) return "status-resolved";
      if (status === "Rejected") return "status-rejected";
      return "status-neutral";
    };

    const renderTimeline = (events) => {
      if (!timeline) return;
      timeline.replaceChildren();
      events.forEach((event) => {
        const item = document.createElement("li");
        item.className = "timeline-event";
        const dot = document.createElement("span");
        dot.className = "timeline-dot";
        const content = document.createElement("div");
        const title = document.createElement("strong");
        title.textContent = event.activity_type;
        const description = document.createElement("p");
        description.textContent = event.log_description;
        const time = document.createElement("time");
        time.textContent = new Date(event.created_at).toLocaleString();
        content.append(title, description, time);
        item.append(dot, content);
        timeline.append(item);
      });
    };

    const refresh = async () => {
      try {
        const response = await fetch(endpoint, {
          headers: { Accept: "application/json" },
          cache: "no-store",
        });
        if (!response.ok) return;
        const data = await response.json();
        if (statusLabels[0] && statusLabels[0].textContent.trim() !== data.status) {
          window.location.reload();
          return;
        }
        statusLabels.forEach((label) => {
          label.textContent = data.status;
          label.className = `status-pill ${statusClass(data.status)}`;
        });
        if (updated) {
          updated.textContent = `Last updated ${new Date(data.updated_at).toLocaleString()}`;
        }
        const signature = JSON.stringify(data.timeline);
        if (signature !== previousSignature) {
          previousSignature = signature;
          renderTimeline(data.timeline);
        }
      } catch {
        // A later poll will retry if the network is temporarily unavailable.
      }
    };

    window.setTimeout(refresh, 1200);
    window.setInterval(refresh, 20000);
  }
})();
