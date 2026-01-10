<?php
/**
 * Viewer Modals - Add and Edit Marker Modals
 * Included by view.php
 */
?>
<!-- Add Marker Modal -->
<div class="modal fade marker-modal" id="addMarkerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pin-map"></i> Add Marker</h5>
                <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addMarkerForm">
                    <input type="hidden" id="markerYaw" name="yaw">
                    <input type="hidden" id="markerPitch" name="pitch">
                    <input type="hidden" id="markerColor" name="color" value="blue">
                    <div class="mb-3">
                        <label for="markerLabel" class="form-label">Label <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="markerLabel" name="label" required maxlength="200" placeholder="Enter a title for this marker">
                    </div>
                    <div class="mb-3">
                        <label for="markerDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="markerDescription" name="description" rows="3" placeholder="Add more details about this location..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Pin Color</label>
                        <div class="color-picker" id="addColorPicker">
                            <div class="color-option selected" data-color="blue" title="Blue"></div>
                            <div class="color-option" data-color="red" title="Red"></div>
                            <div class="color-option" data-color="green" title="Green"></div>
                            <div class="color-option" data-color="yellow" title="Yellow"></div>
                            <div class="color-option" data-color="orange" title="Orange"></div>
                            <div class="color-option" data-color="purple" title="Purple"></div>
                            <div class="color-option" data-color="pink" title="Pink"></div>
                            <div class="color-option" data-color="cyan" title="Cyan"></div>
                            <div class="color-option" data-color="white" title="White"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="markerAudio" class="form-label">
                            <i class="bi bi-volume-up"></i> Attach Audio (Optional)
                        </label>
                        <input type="file" class="form-control" id="markerAudio" name="audio_file" 
                               accept=".mp3,.wav,.ogg,audio/mpeg,audio/wav,audio/ogg">
                        <div class="form-text">MP3, WAV, or OGG. Max 15MB.</div>
                    </div>
                    <div class="mb-3">
                        <label for="markerTargetPanorama" class="form-label">
                            <i class="bi bi-box-arrow-up-right"></i> Link to Scene (Portal Marker)
                        </label>
                        <select class="form-select" id="markerTargetPanorama" name="target_panorama_id">
                            <option value="">No link (regular marker)</option>
                        </select>
                        <div class="form-text">Create a portal that navigates to another panorama when clicked.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveMarkerBtn">
                    <i class="bi bi-check-lg"></i> Save Marker
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Marker Modal -->
<div class="modal fade marker-modal" id="editMarkerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Marker</h5>
                <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editMarkerForm">
                    <input type="hidden" id="editMarkerId" name="id">
                    <input type="hidden" id="editMarkerColor" name="color" value="blue">
                    <div class="mb-3">
                        <label for="editMarkerLabel" class="form-label">Label <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editMarkerLabel" name="label" required maxlength="200">
                    </div>
                    <div class="mb-3">
                        <label for="editMarkerDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="editMarkerDescription" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Pin Color</label>
                        <div class="color-picker" id="editColorPicker">
                            <div class="color-option" data-color="blue" title="Blue"></div>
                            <div class="color-option" data-color="red" title="Red"></div>
                            <div class="color-option" data-color="green" title="Green"></div>
                            <div class="color-option" data-color="yellow" title="Yellow"></div>
                            <div class="color-option" data-color="orange" title="Orange"></div>
                            <div class="color-option" data-color="purple" title="Purple"></div>
                            <div class="color-option" data-color="pink" title="Pink"></div>
                            <div class="color-option" data-color="cyan" title="Cyan"></div>
                            <div class="color-option" data-color="white" title="White"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="bi bi-volume-up"></i> Audio Attachment
                        </label>
                        <div id="editCurrentAudio" class="mb-2 d-none">
                            <div class="d-flex align-items-center gap-2 p-2 bg-light rounded">
                                <i class="bi bi-music-note-beamed text-primary"></i>
                                <span class="flex-grow-1 text-truncate" id="editAudioFilename">audio.mp3</span>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="previewAudioBtn" title="Preview">
                                    <i class="bi bi-play-fill"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="removeAudioBtn" title="Remove audio">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </div>
                        <input type="hidden" id="editRemoveAudio" name="remove_audio" value="0">
                        <input type="file" class="form-control" id="editMarkerAudio" name="audio_file" 
                               accept=".mp3,.wav,.ogg,audio/mpeg,audio/wav,audio/ogg">
                        <div class="form-text">MP3, WAV, or OGG. Max 15MB. Upload to replace existing.</div>
                    </div>
                    <div class="mb-3">
                        <label for="editMarkerTargetPanorama" class="form-label">
                            <i class="bi bi-box-arrow-up-right"></i> Link to Scene (Portal Marker)
                        </label>
                        <select class="form-select" id="editMarkerTargetPanorama" name="target_panorama_id">
                            <option value="">No link (regular marker)</option>
                        </select>
                        <div class="form-text">Create a portal that navigates to another panorama when clicked.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-danger" id="deleteMarkerBtn">
                    <i class="bi bi-trash"></i> Delete
                </button>
                <div>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="updateMarkerBtn">
                        <i class="bi bi-check-lg"></i> Update
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
