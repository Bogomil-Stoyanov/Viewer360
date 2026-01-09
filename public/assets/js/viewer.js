/**
 * Viewer360 - Panorama Viewer JavaScript Module
 * Handles all viewer functionality including markers, audio, voting, etc.
 */

import { Viewer } from "@photo-sphere-viewer/core";
import { MarkersPlugin } from "@photo-sphere-viewer/markers-plugin";

/**
 * Initialize the panorama viewer with all functionality
 * @param {Object} config - Configuration object
 */
export function initViewer(config) {
  const {
    panoramaId,
    panoramaPath,
    panoramaTitle,
    isOwner,
    isLoggedIn,
    highlightMarkerId,
    currentUserId,
    currentUsername,
  } = config;

  // State
  let editMode = false;
  let markersData = [];
  let currentAudio = null;
  let currentPlayingMarkerId = null;
  let userPanoramas = [];

  // Color mapping
  const colorMap = {
    blue: "#0d6efd",
    red: "#dc3545",
    green: "#198754",
    yellow: "#ffc107",
    orange: "#fd7e14",
    purple: "#6f42c1",
    pink: "#d63384",
    cyan: "#0dcaf0",
    white: "#ffffff",
  };

  // Initialize viewer with markers plugin
  const viewer = new Viewer({
    container: document.querySelector("#viewer"),
    panorama: "/" + panoramaPath,
    caption: panoramaTitle,
    loadingTxt: "Loading...",
    defaultYaw: 0,
    defaultPitch: 0,
    navbar: ["autorotate", "zoom", "move", "fullscreen"],
    plugins: [
      [
        MarkersPlugin,
        {
          markers: [],
        },
      ],
    ],
  });

  const markersPlugin = viewer.getPlugin(MarkersPlugin);

  // ========== HELPER FUNCTIONS ==========

  function escapeHtml(text) {
    if (!text) return "";
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  // ========== MARKER FUNCTIONS ==========

  function getMarkerGradient(color, isHighlighted = false) {
    if (isHighlighted) {
      return "linear-gradient(135deg, #ffc107 0%, #fd7e14 100%)";
    }
    const hex = colorMap[color] || colorMap["blue"];
    return `linear-gradient(135deg, ${hex} 0%, ${hex}dd 100%)`;
  }

  function createMarkerConfig(marker, isHighlighted = false) {
    const markerColor = marker.color || "blue";
    const hasAudio = marker.audio_path && marker.audio_path.length > 0;
    const isPortal = marker.target_panorama_id && marker.target_panorama_id > 0;
    const audioIndicator = hasAudio ? '<i class="bi bi-volume-up"></i> ' : "";
    const portalIndicator = isPortal
      ? '<i class="bi bi-box-arrow-up-right"></i> '
      : "";

    let portalInfo = "";
    if (isPortal) {
      const targetPano = userPanoramas.find(
        (p) => parseInt(p.id) === parseInt(marker.target_panorama_id)
      );
      if (targetPano) {
        portalInfo = `<p class="text-success small mb-1"><i class="bi bi-signpost-2"></i> Leads to: ${escapeHtml(
          targetPano.title
        )}</p>`;
      } else {
        portalInfo = `<p class="text-success small mb-1"><i class="bi bi-signpost-2"></i> Portal to another scene</p>`;
      }
    }

    const tooltipContent = `
            <div class="marker-tooltip">
                <h6>${portalIndicator}${audioIndicator}${escapeHtml(
      marker.label
    )}</h6>
                ${
                  marker.description
                    ? `<p>${escapeHtml(marker.description)}</p>`
                    : ""
                }
                ${portalInfo}
                ${
                  hasAudio && !isPortal
                    ? '<p class="text-info small mb-1"><i class="bi bi-music-note"></i> Click marker to play/pause audio</p>'
                    : ""
                }
                ${
                  isPortal
                    ? '<p class="text-warning small mb-1"><i class="bi bi-arrow-right-circle"></i> Click marker to navigate</p>'
                    : ""
                }
                <div class="marker-meta">
                    <i class="bi bi-person"></i> ${escapeHtml(
                      marker.username || "Unknown"
                    )}
                    <button class="btn btn-sm btn-outline-light ms-2 copy-link-btn" 
                            onclick="window.viewerModule.copyMarkerLink(${
                              marker.id
                            }); event.stopPropagation();" 
                            title="Copy link to this marker">
                        <i class="bi bi-link-45deg"></i>
                    </button>
                </div>
            </div>
        `;

    const markerGradient = getMarkerGradient(markerColor, isHighlighted);
    const borderColor = markerColor === "white" ? "#ccc" : "white";
    const audioClass = hasAudio ? "has-audio" : "";
    const portalClass = isPortal ? "is-portal" : "";
    const audioIcon =
      hasAudio && !isPortal ? '<i class="bi bi-volume-up audio-icon"></i>' : "";
    const portalIcon = isPortal
      ? '<i class="bi bi-arrow-right-circle-fill portal-icon"></i>'
      : "";

    return {
      id: `marker-${marker.id}`,
      position: {
        yaw: parseFloat(marker.yaw),
        pitch: parseFloat(marker.pitch),
      },
      html: `<div class="custom-marker ${audioClass} ${portalClass} ${
        isHighlighted ? "highlighted" : ""
      }" 
                        data-marker-id="${marker.id}" 
                        data-has-audio="${hasAudio}"
                        data-audio-path="${hasAudio ? marker.audio_path : ""}"
                        data-is-portal="${isPortal}"
                        data-target-panorama="${
                          isPortal ? marker.target_panorama_id : ""
                        }"
                        style="background: ${markerGradient}; border-color: ${borderColor};">${audioIcon}${portalIcon}</div>`,
      anchor: "center center",
      tooltip: {
        content: tooltipContent,
        position: "top center",
        trigger: "click",
      },
      data: marker,
    };
  }

  // Copy marker deep link to clipboard
  function copyMarkerLink(markerId) {
    const url = `${window.location.origin}/view.php?id=${panoramaId}&marker=${markerId}`;
    navigator.clipboard
      .writeText(url)
      .then(() => {
        const btn = document.querySelector(".copy-link-btn");
        if (btn) {
          const originalHTML = btn.innerHTML;
          btn.innerHTML = '<i class="bi bi-check"></i>';
          setTimeout(() => {
            btn.innerHTML = originalHTML;
          }, 1500);
        }
      })
      .catch(() => {
        prompt("Copy this link:", url);
      });
  }

  // Load markers from API
  async function loadMarkers() {
    try {
      const response = await fetch(
        `/api.php?action=marker/list&panorama_id=${panoramaId}`
      );
      const data = await response.json();

      if (data.success) {
        markersData = data.markers;
        renderMarkers();
      }
    } catch (error) {
      console.error("Failed to load markers:", error);
    }
  }

  // Load user's panoramas for portal linking dropdown
  async function loadUserPanoramas() {
    if (!isOwner) return;

    try {
      const response = await fetch(
        `/api.php?action=panorama/user-list&exclude_id=${panoramaId}`
      );
      const data = await response.json();

      if (data.success) {
        userPanoramas = data.panoramas;
        populatePortalDropdowns();
      }
    } catch (error) {
      console.error("Failed to load user panoramas:", error);
    }
  }

  function populatePortalDropdowns() {
    const addSelect = document.getElementById("markerTargetPanorama");
    const editSelect = document.getElementById("editMarkerTargetPanorama");

    const options = userPanoramas
      .map((p) => `<option value="${p.id}">${escapeHtml(p.title)}</option>`)
      .join("");

    if (addSelect) {
      addSelect.innerHTML =
        '<option value="">No link (regular marker)</option>' + options;
    }
    if (editSelect) {
      editSelect.innerHTML =
        '<option value="">No link (regular marker)</option>' + options;
    }
  }

  function renderMarkers() {
    markersPlugin.clearMarkers();

    markersData.forEach((marker) => {
      const isHighlighted =
        highlightMarkerId && parseInt(marker.id) === highlightMarkerId;
      markersPlugin.addMarker(createMarkerConfig(marker, isHighlighted));
    });

    updateMarkerSidebar();
  }

  function updateMarkerSidebar() {
    const listContainer = document.getElementById("markerList");
    const countBadge = document.getElementById("markerCount");

    countBadge.textContent = markersData.length;

    if (markersData.length === 0) {
      listContainer.innerHTML = `
                <div class="empty-markers">
                    <i class="bi bi-pin-map" style="font-size: 2rem; opacity: 0.3;"></i>
                    <p class="mt-2 mb-0">No markers yet</p>
                </div>
            `;
      return;
    }

    listContainer.innerHTML = markersData
      .map((marker) => {
        const markerColor = marker.color || "blue";
        const colorHex = colorMap[markerColor] || colorMap["blue"];
        const hasAudio = marker.audio_path && marker.audio_path.length > 0;
        const isPortal =
          marker.target_panorama_id && marker.target_panorama_id > 0;
        const audioIcon = hasAudio
          ? '<i class="bi bi-volume-up text-info me-1" title="Has audio"></i>'
          : "";
        const portalIcon = isPortal
          ? '<i class="bi bi-box-arrow-up-right text-success me-1" title="Portal to another scene"></i>'
          : "";
        return `
                <div class="marker-list-item ${
                  isPortal ? "is-portal" : ""
                }" data-marker-id="${
          marker.id
        }" onclick="window.viewerModule.navigateToMarker(${marker.id})">
                    <div class="marker-color-dot" style="background: ${colorHex};"></div>
                    ${portalIcon}${audioIcon}
                    <span class="label">${escapeHtml(marker.label)}</span>
                    <i class="bi bi-chevron-right arrow"></i>
                </div>
            `;
      })
      .join("");
  }

  function navigateToMarker(markerId) {
    animateToMarker(markerId);
    if (window.innerWidth < 768) {
      document.getElementById("markerSidebar").classList.remove("open");
      document.getElementById("sidebarToggle").classList.remove("open");
    }
  }

  async function createMarker(
    yaw,
    pitch,
    label,
    description,
    color,
    audioFile = null,
    targetPanoramaId = null
  ) {
    try {
      const formData = new FormData();
      formData.append("panorama_id", panoramaId);
      formData.append("yaw", yaw);
      formData.append("pitch", pitch);
      formData.append("label", label);
      formData.append("description", description);
      formData.append("color", color);
      formData.append("type", targetPanoramaId ? "portal" : "text");

      if (audioFile) {
        formData.append("audio_file", audioFile);
      }

      if (targetPanoramaId) {
        formData.append("target_panorama_id", targetPanoramaId);
      }

      const response = await fetch("/api.php?action=marker/create", {
        method: "POST",
        body: formData,
      });

      const data = await response.json();

      if (data.success) {
        data.marker.username = currentUsername;
        markersData.push(data.marker);
        markersPlugin.addMarker(createMarkerConfig(data.marker));
        updateMarkerSidebar();
        return true;
      } else {
        alert(data.error || "Failed to create marker");
        return false;
      }
    } catch (error) {
      console.error("Failed to create marker:", error);
      alert("Failed to create marker. Please try again.");
      return false;
    }
  }

  async function updateMarker(
    id,
    label,
    description,
    color,
    audioFile = null,
    removeAudio = false,
    targetPanoramaId = null
  ) {
    try {
      const formData = new FormData();
      formData.append("id", id);
      formData.append("label", label);
      formData.append("description", description);
      formData.append("color", color);
      formData.append("type", targetPanoramaId ? "portal" : "text");
      formData.append("remove_audio", removeAudio ? "1" : "0");

      if (audioFile) {
        formData.append("audio_file", audioFile);
      }

      if (targetPanoramaId) {
        formData.append("target_panorama_id", targetPanoramaId);
      }

      const response = await fetch("/api.php?action=marker/update", {
        method: "POST",
        body: formData,
      });

      const data = await response.json();

      if (data.success) {
        const markerIndex = markersData.findIndex((m) => parseInt(m.id) === id);
        if (markerIndex !== -1) {
          markersData[markerIndex].label = label;
          markersData[markerIndex].description = description;
          markersData[markerIndex].color = color;
          markersData[markerIndex].audio_path = data.marker.audio_path;
          markersData[markerIndex].target_panorama_id =
            data.marker.target_panorama_id;
          markersData[markerIndex].type = data.marker.type;

          markersPlugin.updateMarker(
            createMarkerConfig(markersData[markerIndex])
          );
          updateMarkerSidebar();
        }
        return true;
      } else {
        alert(data.error || "Failed to update marker");
        return false;
      }
    } catch (error) {
      console.error("Failed to update marker:", error);
      alert("Failed to update marker. Please try again.");
      return false;
    }
  }

  async function deleteMarker(id) {
    try {
      const response = await fetch("/api.php?action=marker/delete", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: id }),
      });

      const data = await response.json();

      if (data.success) {
        markersData = markersData.filter((m) => parseInt(m.id) !== id);
        markersPlugin.removeMarker(`marker-${id}`);
        return true;
      } else {
        alert(data.error || "Failed to delete marker");
        return false;
      }
    } catch (error) {
      console.error("Failed to delete marker:", error);
      alert("Failed to delete marker. Please try again.");
      return false;
    }
  }

  // ========== DEEP LINKING ==========

  function animateToMarker(markerId) {
    const marker = markersData.find((m) => parseInt(m.id) === markerId);
    if (marker) {
      viewer
        .animate({
          yaw: parseFloat(marker.yaw),
          pitch: parseFloat(marker.pitch),
          zoom: 50,
          speed: "2rpm",
        })
        .then(() => {
          console.log("Animated to marker:", markerId);
        });
    }
  }

  // ========== FORK/SAVE TO COLLECTION ==========

  async function forkPanorama() {
    try {
      const response = await fetch("/api.php?action=panorama/fork", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ panorama_id: panoramaId }),
      });

      const data = await response.json();

      if (data.success) {
        alert(data.message);
        window.location.href = `/view.php?id=${data.panorama_id}`;
      } else {
        alert(data.error || "Failed to save to collection");
      }
    } catch (error) {
      console.error("Failed to fork panorama:", error);
      alert("Failed to save to collection. Please try again.");
    }
  }

  // ========== AUDIO PLAYBACK FUNCTIONS ==========

  function stopCurrentAudio() {
    if (currentAudio) {
      currentAudio.pause();
      currentAudio.currentTime = 0;
      currentAudio = null;

      if (currentPlayingMarkerId) {
        const markerEl = document.querySelector(
          `[data-marker-id="${currentPlayingMarkerId}"]`
        );
        if (markerEl) {
          markerEl.classList.remove("playing");
        }
      }
      currentPlayingMarkerId = null;
    }
  }

  function toggleMarkerAudio(markerId, audioPath) {
    if (currentPlayingMarkerId === markerId && currentAudio) {
      stopCurrentAudio();
      return;
    }

    stopCurrentAudio();

    currentAudio = new Audio("/" + audioPath);
    currentPlayingMarkerId = markerId;

    const markerEl = document.querySelector(`[data-marker-id="${markerId}"]`);
    if (markerEl) {
      markerEl.classList.add("playing");
    }

    currentAudio.addEventListener("ended", () => {
      if (markerEl) {
        markerEl.classList.remove("playing");
      }
      currentPlayingMarkerId = null;
      currentAudio = null;
    });

    currentAudio.addEventListener("error", () => {
      console.error("Failed to load audio:", audioPath);
      stopCurrentAudio();
    });

    currentAudio.play().catch((err) => {
      console.error("Failed to play audio:", err);
      stopCurrentAudio();
    });
  }

  // ========== PORTAL NAVIGATION ==========

  function navigateToPortal(targetPanoramaId) {
    const overlay = document.createElement("div");
    overlay.className = "portal-transition-overlay";
    overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: black;
            opacity: 0;
            z-index: 9999;
            transition: opacity 0.5s ease;
            pointer-events: none;
        `;
    document.body.appendChild(overlay);

    requestAnimationFrame(() => {
      overlay.style.opacity = "1";
    });

    setTimeout(() => {
      window.location.href = `/view.php?id=${targetPanoramaId}`;
    }, 500);
  }

  // ========== VOTING SYSTEM ==========

  let currentUserVote = 0;
  let currentScore = 0;

  async function loadVoteStatus() {
    try {
      const response = await fetch(
        `/api.php?action=vote/status&panorama_id=${panoramaId}`
      );
      const data = await response.json();

      if (data.success) {
        currentScore = data.score;
        currentUserVote = data.userVote;
        updateVoteUI();
      }
    } catch (error) {
      console.error("Failed to load vote status:", error);
    }
  }

  function updateVoteUI() {
    const scoreEl = document.getElementById("voteScore");
    const upBtn = document.getElementById("upvoteBtn");
    const downBtn = document.getElementById("downvoteBtn");

    if (!scoreEl) return;

    scoreEl.textContent = currentScore;
    scoreEl.classList.remove("positive", "negative");
    if (currentScore > 0) scoreEl.classList.add("positive");
    else if (currentScore < 0) scoreEl.classList.add("negative");

    if (upBtn) {
      upBtn.classList.toggle("active", currentUserVote === 1);
    }
    if (downBtn) {
      downBtn.classList.toggle("active", currentUserVote === -1);
    }
  }

  async function castVote(value) {
    if (!isLoggedIn) {
      window.location.href = "/login.php";
      return;
    }

    try {
      const response = await fetch("/api.php?action=vote/toggle", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          panorama_id: panoramaId,
          value: value,
        }),
      });

      const data = await response.json();

      if (data.success) {
        currentScore = data.score;
        currentUserVote = data.userVote;
        updateVoteUI();
      } else {
        if (data.error) alert(data.error);
      }
    } catch (error) {
      console.error("Failed to vote:", error);
    }
  }

  // ========== EXPORT DATA ==========

  async function exportData() {
    try {
      const response = await fetch(
        `/api.php?action=panorama/export&panorama_id=${panoramaId}`
      );
      const data = await response.json();

      if (!response.ok) {
        alert(data.error || "Failed to export data");
        return;
      }

      const blob = new Blob([JSON.stringify(data, null, 2)], {
        type: "application/json",
      });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `panorama-${panoramaId}-export.json`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    } catch (error) {
      console.error("Failed to export:", error);
      alert("Failed to export data. Please try again.");
    }
  }

  // ========== EVENT HANDLERS SETUP ==========

  function setupEventHandlers() {
    // Hide loading overlay and initialize when ready
    viewer.addEventListener("ready", () => {
      document.getElementById("loadingOverlay").classList.add("hidden");

      loadUserPanoramas().then(() => {
        loadMarkers().then(() => {
          if (highlightMarkerId) {
            setTimeout(() => animateToMarker(highlightMarkerId), 500);
          }
        });
      });
    });

    // Edit mode toggle
    const editModeToggle = document.getElementById("editModeToggle");
    const editModeIndicator = document.getElementById("editModeIndicator");

    if (editModeToggle) {
      editModeToggle.addEventListener("change", (e) => {
        editMode = e.target.checked;
        editModeIndicator.classList.toggle("active", editMode);
      });
    }

    // Click on sphere to add marker (when in edit mode)
    viewer.addEventListener("click", (e) => {
      if (!editMode || !isOwner) return;

      const position = e.data.rightclick ? null : e.data;
      if (!position || !position.yaw || !position.pitch) return;

      document.getElementById("markerYaw").value = position.yaw;
      document.getElementById("markerPitch").value = position.pitch;
      document.getElementById("markerLabel").value = "";
      document.getElementById("markerDescription").value = "";
      document.getElementById("markerColor").value = "blue";
      document.getElementById("markerAudio").value = "";
      document.getElementById("markerTargetPanorama").value = "";

      document
        .querySelectorAll("#addColorPicker .color-option")
        .forEach((opt) => {
          opt.classList.toggle("selected", opt.dataset.color === "blue");
        });

      document.getElementById("addMarkerModal").classList.add("show");
    });

    // Color picker click handlers
    document
      .querySelectorAll("#addColorPicker .color-option")
      .forEach((option) => {
        option.addEventListener("click", () => {
          document
            .querySelectorAll("#addColorPicker .color-option")
            .forEach((o) => o.classList.remove("selected"));
          option.classList.add("selected");
          document.getElementById("markerColor").value = option.dataset.color;
        });
      });

    document
      .querySelectorAll("#editColorPicker .color-option")
      .forEach((option) => {
        option.addEventListener("click", () => {
          document
            .querySelectorAll("#editColorPicker .color-option")
            .forEach((o) => o.classList.remove("selected"));
          option.classList.add("selected");
          document.getElementById("editMarkerColor").value =
            option.dataset.color;
        });
      });

    // Save marker button
    document
      .getElementById("saveMarkerBtn")
      .addEventListener("click", async () => {
        const yaw = parseFloat(document.getElementById("markerYaw").value);
        const pitch = parseFloat(document.getElementById("markerPitch").value);
        const label = document.getElementById("markerLabel").value.trim();
        const description = document
          .getElementById("markerDescription")
          .value.trim();
        const color = document.getElementById("markerColor").value;
        const audioInput = document.getElementById("markerAudio");
        const audioFile =
          audioInput.files.length > 0 ? audioInput.files[0] : null;
        const targetPanoramaSelect = document.getElementById(
          "markerTargetPanorama"
        );
        const targetPanoramaId = targetPanoramaSelect.value
          ? parseInt(targetPanoramaSelect.value)
          : null;

        if (!label) {
          alert("Please enter a label for the marker");
          return;
        }

        if (audioFile && audioFile.size > 15 * 1024 * 1024) {
          alert("Audio file must be less than 15MB");
          return;
        }

        const success = await createMarker(
          yaw,
          pitch,
          label,
          description,
          color,
          audioFile,
          targetPanoramaId
        );
        if (success) {
          document.getElementById("addMarkerModal").classList.remove("show");
          audioInput.value = "";
        }
      });

    // Click on marker to edit or play audio or navigate
    markersPlugin.addEventListener("select-marker", (e) => {
      const markerData = e.marker.config.data;

      if (markerData && markerData.target_panorama_id && !editMode) {
        navigateToPortal(markerData.target_panorama_id);
        return;
      }

      if (markerData && markerData.audio_path && !editMode) {
        toggleMarkerAudio(markerData.id, markerData.audio_path);
        return;
      }

      if (editMode && isOwner && markerData) {
        if (parseInt(markerData.user_id) !== currentUserId) {
          alert("You can only edit your own markers.");
          return;
        }

        document.getElementById("editMarkerId").value = markerData.id;
        document.getElementById("editMarkerLabel").value =
          markerData.label || "";
        document.getElementById("editMarkerDescription").value =
          markerData.description || "";
        document.getElementById("editMarkerColor").value =
          markerData.color || "blue";
        document.getElementById("editRemoveAudio").value = "0";
        document.getElementById("editMarkerAudio").value = "";

        const targetPanoramaSelect = document.getElementById(
          "editMarkerTargetPanorama"
        );
        if (targetPanoramaSelect) {
          targetPanoramaSelect.value = markerData.target_panorama_id || "";
        }

        const currentAudioDiv = document.getElementById("editCurrentAudio");
        if (markerData.audio_path) {
          currentAudioDiv.classList.remove("d-none");
          document.getElementById("editAudioFilename").textContent =
            markerData.audio_path.split("/").pop();
          document.getElementById("editAudioFilename").dataset.path =
            markerData.audio_path;
        } else {
          currentAudioDiv.classList.add("d-none");
        }

        document
          .querySelectorAll("#editColorPicker .color-option")
          .forEach((opt) => {
            opt.classList.toggle(
              "selected",
              opt.dataset.color === (markerData.color || "blue")
            );
          });

        document.getElementById("editMarkerModal").classList.add("show");
      }
    });

    // Update marker button
    document
      .getElementById("updateMarkerBtn")
      .addEventListener("click", async () => {
        const id = parseInt(document.getElementById("editMarkerId").value);
        const label = document.getElementById("editMarkerLabel").value.trim();
        const description = document
          .getElementById("editMarkerDescription")
          .value.trim();
        const color = document.getElementById("editMarkerColor").value;
        const removeAudio =
          document.getElementById("editRemoveAudio").value === "1";
        const audioInput = document.getElementById("editMarkerAudio");
        const audioFile =
          audioInput.files.length > 0 ? audioInput.files[0] : null;
        const targetPanoramaSelect = document.getElementById(
          "editMarkerTargetPanorama"
        );
        const targetPanoramaId =
          targetPanoramaSelect && targetPanoramaSelect.value
            ? parseInt(targetPanoramaSelect.value)
            : null;

        if (!label) {
          alert("Please enter a label for the marker");
          return;
        }

        if (audioFile && audioFile.size > 15 * 1024 * 1024) {
          alert("Audio file must be less than 15MB");
          return;
        }

        const success = await updateMarker(
          id,
          label,
          description,
          color,
          audioFile,
          removeAudio,
          targetPanoramaId
        );
        if (success) {
          document.getElementById("editMarkerModal").classList.remove("show");
        }
      });

    // Delete marker button
    document
      .getElementById("deleteMarkerBtn")
      .addEventListener("click", async () => {
        if (!confirm("Are you sure you want to delete this marker?")) return;

        const id = parseInt(document.getElementById("editMarkerId").value);
        const success = await deleteMarker(id);
        if (success) {
          document.getElementById("editMarkerModal").classList.remove("show");
        }
      });

    // Save to collection button
    const saveToCollectionBtn = document.getElementById("saveToCollectionBtn");
    if (saveToCollectionBtn) {
      saveToCollectionBtn.addEventListener("click", () => {
        if (
          confirm(
            "Save this panorama to your collection? You will be able to add your own markers."
          )
        ) {
          forkPanorama();
        }
      });
    }

    // Vote button handlers
    const upvoteBtn = document.getElementById("upvoteBtn");
    const downvoteBtn = document.getElementById("downvoteBtn");

    if (upvoteBtn) {
      upvoteBtn.addEventListener("click", () => castVote(1));
    }
    if (downvoteBtn) {
      downvoteBtn.addEventListener("click", () => castVote(-1));
    }

    // Sidebar toggle
    const sidebarToggle = document.getElementById("sidebarToggle");
    const markerSidebar = document.getElementById("markerSidebar");

    if (sidebarToggle && markerSidebar) {
      sidebarToggle.addEventListener("click", () => {
        sidebarToggle.classList.toggle("open");
        markerSidebar.classList.toggle("open");
      });
    }

    // Export data button
    const exportDataBtn = document.getElementById("exportDataBtn");
    if (exportDataBtn) {
      exportDataBtn.addEventListener("click", exportData);
    }

    // Load vote status on page load
    if (document.getElementById("votingPanel")) {
      loadVoteStatus();
    }

    // Preview audio button
    const previewAudioBtn = document.getElementById("previewAudioBtn");
    if (previewAudioBtn) {
      let previewAudio = null;
      previewAudioBtn.addEventListener("click", () => {
        const audioPath =
          document.getElementById("editAudioFilename").dataset.path;
        if (!audioPath) return;

        if (previewAudio) {
          previewAudio.pause();
          previewAudio = null;
          previewAudioBtn.innerHTML = '<i class="bi bi-play-fill"></i>';
        } else {
          previewAudio = new Audio("/" + audioPath);
          previewAudioBtn.innerHTML = '<i class="bi bi-pause-fill"></i>';
          previewAudio.play();
          previewAudio.addEventListener("ended", () => {
            previewAudioBtn.innerHTML = '<i class="bi bi-play-fill"></i>';
            previewAudio = null;
          });
        }
      });
    }

    // Remove audio button
    const removeAudioBtn = document.getElementById("removeAudioBtn");
    if (removeAudioBtn) {
      removeAudioBtn.addEventListener("click", () => {
        if (confirm("Remove audio from this marker?")) {
          document.getElementById("editRemoveAudio").value = "1";
          document.getElementById("editCurrentAudio").classList.add("d-none");
        }
      });
    }

    // Audio file input validation
    document
      .getElementById("markerAudio")
      ?.addEventListener("change", function () {
        if (this.files.length > 0 && this.files[0].size > 15 * 1024 * 1024) {
          alert("Audio file must be less than 15MB");
          this.value = "";
        }
      });

    document
      .getElementById("editMarkerAudio")
      ?.addEventListener("change", function () {
        if (this.files.length > 0 && this.files[0].size > 15 * 1024 * 1024) {
          alert("Audio file must be less than 15MB");
          this.value = "";
        }
      });
  }

  // Initialize event handlers
  setupEventHandlers();

  // Expose methods to window for onclick handlers in HTML
  return {
    copyMarkerLink,
    navigateToMarker,
  };
}
