<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Participant Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/cropper.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/participant-form.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/navbar.css') }}">
</head>

<body>
    <nav class="navbar navbar-dark app-navbar">
        <div class="container-fluid"><a class="navbar-brand" href="/">Participant Registration</a></div>
    </nav>

    <div class="container" style="max-width:900px; margin-top:24px;">
        <div class="form-container">
            <div class="form-header">
                <img src="{{ asset('images/logo.webp') }}" alt="Tournament Logo" class="form-logo">
                <h2>Player Registration</h2>
            </div>
            <div class="form-body">
                <div class="alert alert-danger alert-error" id="errorAlert"></div>

                <form id="registrationForm" method="POST" action="{{ route('form.submit') }}"
                    enctype="multipart/form-data">
                    @csrf
                    <div class="row">

                        <div class="col-md-12 mb-3">
                            <label class="form-label required" for="league_type">LEAGUE</label>
                            <select class="form-select" id="league_type" name="league_type" required>
                                <option value="">Select League</option>
                                @foreach(($leagues ?? collect()) as $league)
                                    <option value="{{ $league->slug }}">{{ $league->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label required" for="first_name">FIRST NAME</label>
                            <input class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label required" for="last_name">LAST NAME</label>
                            <input class="form-control" id="last_name" name="last_name" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label required" for="nick_name"
                                title="Nick Name or Alias is a pseudo name of a person used instead of their real name">NICK
                                NAME/ALIAS</label>
                            <input class="form-control" id="nick_name" name="nick_name"
                                title="Nick Name or Alias is a pseudo name of a person used instead of their real name"
                                required>
                        </div>
                    </div>


                    <div class="section-title required">SKILL CATEGORY</div>
                    <br />
                    <div class="checkbox-group mb-3" id="skillsContainer">
                        @php
                        $skills = [
                        'Right Hand Batsman','Left Hand Batsman','Right-Arm Fast','Left-Arm Fast',
                        'All Rounder','Right Arm Leg Spin','Left Arm Spinner','Off Spinner','Wicket Keeper'
                        ];
                        @endphp

                        @foreach($skills as $i => $s)
                        <div class="checkbox-item">
                            <input type="checkbox" id="skill_{{ $i }}" name="skill_categories[]" value="{{ $s }}">
                            <label for="skill_{{ $i }}">{{ $s }}</label>
                        </div>
                        @endforeach
                    </div>

                    <span class="text-danger d-none" id="skillsError">Please select at least one skill.</span>

                    <div class="mb-3">
                        <label class="form-label required" for="performance"
                            title="Enter your cricket performance details including batting style (right/left-hand), bowling type (pace/spin), and primary role in the team (batsman, bowler, all-rounder, wicketkeeper).
You may also mention previous tournament participation, notable achievements, match statistics, or any awards received.">PERFORMANCE</label>
                        <textarea class="form-control" id="performance" name="performance"
                            style="field-sizing: content;"
                            title="Enter your cricket performance details including batting style (right/left-hand), bowling type (pace/spin), and primary role in the team (batsman, bowler, all-rounder, wicketkeeper).
You may also mention previous tournament participation, notable achievements, match statistics, or any awards received."></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required" for="city">CITY</label>
                            <input class="form-control" id="city" name="city" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label required" for="address">ADDRESS</label>
                            <input class="form-control" id="address" name="address" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required" for="mobile">MOBILE</label>
                            <input class="form-control" id="mobile" name="mobile" required placeholder="+1234567890">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label required" for="email">EMAIL</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required" for="emergency_contact">Emergency Contact</label>
                            <input class="form-control" id="emergency_contact" name="emergency_contact" required
                                placeholder="+1234567890">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label required" for="shirt_number">SHIRT NUMBER</label>
                            <input class="form-control" id="shirt_number" name="shirt_number" required>
                        </div>
                        <!-- <div class="col-md-6 mb-3">
                            <label class="form-label required" for="medical_info">Medical Information</label>
                            <textarea class="form-control" id="medical_info" name="medical_info"
                                style="field-sizing: content;" required></textarea>
                        </div> -->
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required" for="dob">DOB</label>
                            <input type="date" class="form-control" id="dob" name="dob" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label required" for="kit_size">KIT SIZE</label>
                            <select class="form-select" id="kit_size" name="kit_size" required>
                                <option value="">Select</option>
                                <option value="small">small</option>
                                <option value="medium">medium</option>
                                <option value="large">large</option>
                                <option value="xl">XL</option>
                                <option value="xxl">XXL</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">

                        <div class="col-md-6 mb-3">
                            <label class="form-label required" for="nationality">NATIONALITY</label>
                            <select class="form-select" id="nationality" name="nationality" required>
                                <option value="">Select Nationality</option>
                                @php
                                $nationalities =
                                ['Afghanistan','Albania','Algeria','Andorra','Angola','Argentina','Armenia','Australia','Austria','Azerbaijan','Bahamas','Bahrain','Bangladesh','Barbados','Belarus','Belgium','Belize','Benin','Bhutan','Bolivia','Bosnia
                                and Herzegovina','Botswana','Brazil','Brunei','Bulgaria','Burkina
                                Faso','Burundi','Cambodia','Cameroon','Canada','Cape Verde','Central African
                                Republic','Chad','Chile','China','Colombia','Comoros','Congo','Costa
                                Rica','Croatia','Cuba','Cyprus','Czech
                                Republic','Denmark','Djibouti','Dominica','Dominican Republic','Ecuador','Egypt','El
                                Salvador','Equatorial
                                Guinea','Eritrea','Estonia','Ethiopia','Fiji','Finland','France','Gabon','Gambia','Georgia','Germany','Ghana','Greece','Grenada','Guatemala','Guinea','Guinea-Bissau','Guyana','Haiti','Honduras','Hungary','Iceland','Indonesia','Iran','Iraq','Ireland','Italy','Jamaica','Japan','Jordan','Kazakhstan','Kenya','Kuwait','Kyrgyzstan','Laos','Latvia','Lebanon','Lesotho','Liberia','Libya','Liechtenstein','Lithuania','Luxembourg','Madagascar','Malawi','Malaysia','Maldives','Mali','Malta','Mauritania','Mauritius','Mexico','Moldova','Monaco','Mongolia','Montenegro','Morocco','Mozambique','Myanmar','Namibia','Nepal','Netherlands','New
                                Zealand','Nicaragua','Niger','Nigeria','Norway','Oman','Pakistan','Palestine','Panama','Papua
                                New
                                Guinea','Paraguay','Peru','Philippines','Poland','Portugal','Qatar','Romania','Russia','Rwanda','Saudi
                                Arabia','Senegal','Serbia','Singapore','Slovakia','Slovenia','Somalia','South
                                Africa','South Korea','Spain','Sri
                                Lanka','Sudan','Suriname','Sweden','Switzerland','Syria','Taiwan','Tajikistan','Tanzania','Thailand','Togo','Trinidad
                                and Tobago','Tunisia','Turkey','Turkmenistan','Uganda','Ukraine','United Arab
                                Emirates','United Kingdom','United
                                States','Uruguay','Uzbekistan','Venezuela','Vietnam','Yemen','Zambia','Zimbabwe'];
                                $nationalities = array_filter($nationalities, function($n){ return $n !== 'India' && $n
                                !== 'Israel'; });
                                sort($nationalities);
                                @endphp
                                @foreach($nationalities as $nation)
                                <option value="{{ $nation }}">{{ $nation }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label required" for="identity" id="identityLabel">CNIC (9-14
                                alphanumeric)</label>
                            <input class="form-control" id="identity" name="identity" pattern="^[A-Za-z0-9]{9,14}$"
                                required>
                        </div>
                    </div>

                    <div class="row">

                        <div class="mb-3">
                            <label class="form-label required"
                                title="Passport-size formal photograph with plain white background, neutral expression, direct eye contact, even lighting, and professional attire suitable for official records.">PICTURE
                                (passport size)</label>
                            <div class="row">
                                <div class="col-md-9 mb-3"
                                    style="display: flex;justify-content: center;align-items: center;">
                                    <div class="file-upload-area" id="photoArea" style="flex-grow:4;">
                                        <div class="file-upload-icon"><i class="bi bi-cloud-upload"></i></div>
                                        <p
                                            title="Passport-size formal photograph with plain white background, neutral expression, direct eye contact, even lighting, and professional attire suitable for official records.">
                                            Drag & drop or click to select</p>
                                        <input type="file" id="passport_picture" name="passport_picture" accept="image/*"
                                            style="display:none;" required>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <img src="{{asset('assets/images/reference.webp')}}" alt="Passport Size" class="mb-2"
                                        style="border-radius:8px; width:150px;"
                                        title="Passport-size formal photograph with plain white background, neutral expression, direct eye contact, even lighting, and professional attire suitable for official records.">
                                    <small>Example of acceptable photo</small>
                                </div>
                            </div>
                            <div class="file-preview" id="photoPreview">Selected: <span id="photoName"></span></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label required"
                                id="id_picture_document"
                                title="ID card or national identity card photograph with plain white background, neutral expression, direct eye contact, even lighting, and professional attire suitable for official records.">PICTURE
                                (CNIC/IDENTITY)</label>
                            <div class="row">
                                <div class="col-md-9 mb-3"
                                    style="display: flex;justify-content: center;align-items: center;">
                                    <div class="file-upload-area" id="photoArea_id" style="flex-grow:4;">
                                        <div class="file-upload-icon"><i class="bi bi-cloud-upload"></i></div>
                                        <p
                                            title="ID card or national identity card photograph with plain white background, neutral expression, direct eye contact, even lighting, and professional attire suitable for official records.">
                                            Drag & drop or click to select</p>
                                        <input type="file" id="id_picture" name="id_picture" accept="image/*"
                                            style="display:none;" required>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <img src="{{asset('assets/images/cnic_front.webp')}}" alt="ID Card" class="mb-2"
                                        style="border-radius:8px; width:150px;"
                                        title="ID card or national identity card photograph with plain white background, neutral expression, direct eye contact, even lighting, and professional attire suitable for official records.">
                                    <small>Example of acceptable photo</small>
                                </div>
                            </div>
                            <div class="file-preview" id="photoPreview_id">Selected: <span id="photoName_id"></span></div>
                        </div>
                    </div>

                    <div id="travelBlock"
                        style="display:none; border:1px solid #dee2e6; padding:16px; border-radius:8px; margin-bottom:16px; box-shadow: 3px 3px 15px 3px #888888;">
                        <h4>Travel Information</h4>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="airline">AIRLINE</label>
                                <input class="form-control" id="airline" name="airline">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label" for="arrival_date">ARRIVAL DATE</label>
                                <input type="date" class="form-control" id="arrival_date" name="arrival_date">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label" for="arrival_time">ARRIVAL TIME</label>
                                <input type="time" class="form-control" id="arrival_time" name="arrival_time">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="hotel_name">HOTEL NAME</label>
                                <input class="form-control" id="hotel_name" name="hotel_name">
                            </div>
                            {{-- <div class="col-md-6 mb-3">
                                <label class="form-label">HOTEL RESERVATION (PDF)</label>
                                <div class="file-upload-area" id="hotelArea">
                                    <div class="file-upload-icon"><i class="bi bi-file-earmark-pdf"></i></div>
                                    <p>Upload hotel reservation (PDF)</p>
                                    <input type="file" id="hotel_reservation" name="hotel_reservation"
                                        accept="application/pdf" style="display:none;">
                                </div>
                                <div class="file-preview" id="hotelPreview">Selected: <span id="hotelName"></span></div>

                                <label class="form-label mt-3">FLIGHT RESERVATION (PDF)</label>
                                <div class="file-upload-area" id="flightArea">
                                    <div class="file-upload-icon"><i class="bi bi-file-earmark-pdf"></i></div>
                                    <p>Upload flight reservation (PDF)</p>
                                    <input type="file" id="flight_reservation" name="flight_reservation"
                                        accept="application/pdf" style="display:none;">
                                </div>
                                <div class="file-preview" id="flightPreview">Selected: <span id="flightName"></span>
                                </div>
                            </div> --}}
                            <div class="col-md-3 mb-3">
                                <label class="form-label" for="checkin">CHECKIN</label>
                                <input type="date" class="form-control" id="checkin" name="checkin">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label" for="checkout">CHECKOUT</label>
                                <input type="date" class="form-control" id="checkout" name="checkout">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-submit">Submit Registration</button>
                </form>

                <div class="alert alert-success mt-4" id="successMessage" style="display:none;">
                    <div class="success-icon"><i class="bi bi-check-circle"></i></div>
                    <h4>Registration Successful</h4>
                    <p>Thank you. A confirmation email will be sent to you.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="spinner-container" id="spinner">
        <div class="spinner"></div>
    </div>

    <!-- Crop Modal for Passport Picture (Exact 7:9 Aspect Ratio) -->
    <div class="modal fade" id="cropModal" tabindex="-1" aria-labelledby="cropModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="cropModalLabel"><i class="bi bi-crop"></i> Crop Passport Photo (7:9 Ratio)</h5>
                    <button type="button" class="btn-close" id="btnCancelCropX" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="bi bi-info-circle"></i> Please crop the photo to the required passport dimensions (7:9 ratio). Drag and adjust the box, then click "Crop & Save".
                    </div>
                    <div class="row">
                        <div class="col-md-8">
                            <div style="max-height: 400px; background-color: #f8f9fa; display: flex; align-items: center; justify-content: center; overflow: hidden; border-radius: 6px;">
                                <img id="cropImage" src="" alt="Photo to crop" style="max-width: 100%; display: block;">
                            </div>
                            <div class="d-flex gap-2 justify-content-center mt-3">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnZoomIn" title="Zoom In"><i class="bi bi-zoom-in"></i></button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnZoomOut" title="Zoom Out"><i class="bi bi-zoom-out"></i></button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnRotateLeft" title="Rotate Left"><i class="bi bi-arrow-counterclockwise"></i></button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnRotateRight" title="Rotate Right"><i class="bi bi-arrow-clockwise"></i></button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnResetCrop" title="Reset"><i class="bi bi-arrow-repeat"></i> Reset</button>
                            </div>
                        </div>
                        <div class="col-md-4 text-center">
                            <h6 class="text-muted mb-2">Live Preview (7:9)</h6>
                            <div style="width: 140px; height: 180px; margin: 0 auto; overflow: hidden; border: 2px dashed #0d6efd; border-radius: 8px; background-color: #f1f3f5;" id="cropResultPreview">
                            </div>
                            <small class="text-muted d-block mt-2">Required Passport Format</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="btnCancelCrop" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="btnApplyCrop"><i class="bi bi-check-lg"></i> Crop & Save</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('assets/js/cropper.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Crop mechanism for Passport Photo (Exact 7:9 Aspect Ratio)
            const photoArea = document.getElementById('photoArea');
            const photoInput = document.getElementById('passport_picture');
            const photoPreview = document.getElementById('photoPreview');
            const photoName = document.getElementById('photoName');
            const cropModalEl = document.getElementById('cropModal');
            const cropModal = new bootstrap.Modal(cropModalEl);
            const cropImage = document.getElementById('cropImage');
            const btnApplyCrop = document.getElementById('btnApplyCrop');
            const btnCancelCrop = document.getElementById('btnCancelCrop');
            const btnCancelCropX = document.getElementById('btnCancelCropX');
            let cropper = null;
            let originalFilename = '';
            let croppedFileObject = null;

            photoArea.addEventListener('click', (e) => {
                if (e.target !== photoInput) {
                    photoInput.click();
                }
            });
            photoInput.addEventListener('click', (e) => e.stopPropagation());
            photoArea.addEventListener('dragover', e => {
                e.preventDefault();
                photoArea.classList.add('dragover');
            });
            photoArea.addEventListener('dragleave', () => photoArea.classList.remove('dragover'));
            photoArea.addEventListener('drop', e => {
                e.preventDefault();
                photoArea.classList.remove('dragover');
                if (e.dataTransfer.files && e.dataTransfer.files.length) {
                    handlePhotoFile(e.dataTransfer.files[0]);
                }
            });

            photoInput.addEventListener('change', () => {
                if (photoInput.files && photoInput.files.length) {
                    if (photoInput.files[0] !== croppedFileObject) {
                        handlePhotoFile(photoInput.files[0]);
                    }
                }
            });

            function handlePhotoFile(file) {
                if (!file || !file.type.startsWith('image/')) {
                    alert('Please select a valid image file (JPEG, PNG).');
                    return;
                }
                originalFilename = file.name;
                const reader = new FileReader();
                reader.onload = (e) => {
                    cropImage.src = e.target.result;
                    cropModal.show();
                };
                reader.readAsDataURL(file);
            }

            cropModalEl.addEventListener('shown.bs.modal', function () {
                if (cropper) {
                    cropper.destroy();
                }
                cropper = new Cropper(cropImage, {
                    aspectRatio: 7 / 9,
                    viewMode: 1,
                    autoCropArea: 0.9,
                    responsive: true,
                    preview: '#cropResultPreview'
                });
            });

            cropModalEl.addEventListener('hidden.bs.modal', function () {
                if (cropper) {
                    cropper.destroy();
                    cropper = null;
                }
                if (!croppedFileObject && (!photoInput.files || photoInput.files.length === 0)) {
                    photoInput.value = '';
                    photoPreview.classList.remove('show');
                }
            });

            document.getElementById('btnZoomIn').addEventListener('click', () => cropper && cropper.zoom(0.1));
            document.getElementById('btnZoomOut').addEventListener('click', () => cropper && cropper.zoom(-0.1));
            document.getElementById('btnRotateLeft').addEventListener('click', () => cropper && cropper.rotate(-90));
            document.getElementById('btnRotateRight').addEventListener('click', () => cropper && cropper.rotate(90));
            document.getElementById('btnResetCrop').addEventListener('click', () => cropper && cropper.reset());

            function cancelCrop() {
                cropModal.hide();
                if (!croppedFileObject) {
                    photoInput.value = '';
                    photoPreview.classList.remove('show');
                }
            }
            btnCancelCrop.addEventListener('click', cancelCrop);
            btnCancelCropX.addEventListener('click', cancelCrop);

            btnApplyCrop.addEventListener('click', function () {
                if (!cropper) return;
                const canvas = cropper.getCroppedCanvas({
                    width: 700,
                    height: 900,
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high'
                });

                canvas.toBlob(function (blob) {
                    if (!blob) {
                        alert('Could not crop the image. Please try again.');
                        return;
                    }
                    const nameParts = originalFilename.split('.');
                    if (nameParts.length > 1) {
                        nameParts.pop();
                    }
                    const newName = nameParts.join('.') + '_cropped_7x9.jpg';

                    croppedFileObject = new File([blob], newName, { type: 'image/jpeg' });
                    const dt = new DataTransfer();
                    dt.items.add(croppedFileObject);
                    photoInput.files = dt.files;

                    photoPreview.classList.add('show');
                    photoName.innerHTML = '<strong>' + newName + '</strong> <span class="badge bg-success ms-2">Cropped (7:9)</span>';

                    cropModal.hide();
                }, 'image/jpeg', 0.92);
            });

            const photoAreaId = document.getElementById('photoArea_id');
            const idInput = document.getElementById('id_picture');
            const idPreview = document.getElementById('photoPreview_id');
            const idName = document.getElementById('photoName_id');
            photoAreaId.addEventListener('click', () => idInput.click());
            photoAreaId.addEventListener('dragover', e => {
                e.preventDefault();
                photoAreaId.classList.add('dragover');
            });
            photoAreaId.addEventListener('dragleave', () => photoAreaId.classList.remove('dragover'));
            photoAreaId.addEventListener('drop', e => {
                e.preventDefault();
                photoAreaId.classList.remove('dragover');
                idInput.files = e.dataTransfer.files;
                updateId();
            });
            idInput.addEventListener('change', updateId);

            function updateId() {
                if (idInput.files.length) {
                    idPreview.classList.add('show');
                    idName.textContent = idInput.files[0].name
                } else {
                    idPreview.classList.remove('show')
                }
            }

            // Hotel reservation upload
            // const hotelArea = document.getElementById('hotelArea');
            // const hotelInput = document.getElementById('hotel_reservation');
            // const hotelPreview = document.getElementById('hotelPreview');
            // const hotelName = document.getElementById('hotelName');
            // hotelArea.addEventListener('click', () => hotelInput.click());
            // hotelArea.addEventListener('dragover', e=>{ e.preventDefault(); hotelArea.classList.add('dragover'); });
            // hotelArea.addEventListener('dragleave', ()=> hotelArea.classList.remove('dragover'));
            // hotelArea.addEventListener('drop', e=>{ e.preventDefault(); hotelArea.classList.remove('dragover'); hotelInput.files = e.dataTransfer.files; updateHotel(); });
            // hotelInput.addEventListener('change', updateHotel);
            // function updateHotel(){ if(hotelInput.files.length){ hotelPreview.classList.add('show'); hotelName.textContent = hotelInput.files[0].name } else { hotelPreview.classList.remove('show') } }

            // Flight reservation upload
            // const flightArea = document.getElementById('flightArea');
            // const flightInput = document.getElementById('flight_reservation');
            // const flightPreview = document.getElementById('flightPreview');
            // const flightName = document.getElementById('flightName');
            // flightArea.addEventListener('click', () => flightInput.click());
            // flightArea.addEventListener('dragover', e=>{ e.preventDefault(); flightArea.classList.add('dragover'); });
            // flightArea.addEventListener('dragleave', ()=> flightArea.classList.remove('dragover'));
            // flightArea.addEventListener('drop', e=>{ e.preventDefault(); flightArea.classList.remove('dragover'); flightInput.files = e.dataTransfer.files; updateFlight(); });
            // flightInput.addEventListener('change', updateFlight);
            // function updateFlight(){ if(flightInput.files.length){ flightPreview.classList.add('show'); flightName.textContent = flightInput.files[0].name } else { flightPreview.classList.remove('show') } }

            // toggle travel-related fields based on nationality
            const nationalitySelect = document.getElementById('nationality');
            const travelBlock = document.getElementById('travelBlock');
            const identityLabel = document.getElementById('identityLabel');
            const identityPicture = document.getElementById('id_picture_document');

            function toggleRelevantFields() {
                const val = nationalitySelect.value || '';
                const show = val !== '' && val !== 'Pakistan';
                travelBlock.style.display = show ? '' : 'none';
                identityLabel.textContent = show ? 'Passport Number' : 'CNIC/ID CARD';
                identityPicture.textContent = show ? 'Passport Picture' : 'CNIC/ID CARD';
                // enable/disable contained controls to avoid browser validation on hidden fields
                const controls = travelBlock.querySelectorAll('input, select, textarea');
                controls.forEach(c => {
                    c.disabled = !show;
                });
            }
            nationalitySelect.addEventListener('change', toggleRelevantFields);
            toggleRelevantFields();

            // Form submit
            document.getElementById('registrationForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const checkboxes = document.querySelectorAll('input[name="skill_categories[]"]');
                const error = document.getElementById("skillsError");
                let checked = false;

                checkboxes.forEach(cb => {
                    if (cb.checked) {
                        checked = true;
                    }
                });

                if (!checked) {
                    e.preventDefault();
                    error.classList.remove("d-none");
                } else {
                    error.classList.add("d-none");
                }
                const selectedSkills = document.querySelectorAll('input[name="skill_categories[]"]:checked');
                if (selectedSkills.length === 0) {
                    showError('Please select at least one skill category');
                    return;
                }
                const spinner = document.getElementById('spinner');
                spinner.classList.add('show');
                const fd = new FormData(this);
                // Ensure skill_categories[] values are present
                fetch('{{ route("form.submit") }}', {
                        method: 'POST',
                        body: fd,
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json'
                        }
                    })
                    .then(r => r.json().catch(() => ({
                        success: false,
                        message: 'Invalid server response'
                    }))).then(data => {
                        spinner.classList.remove('show');
                        if (data.success) {
                            document.getElementById('registrationForm').style.display = 'none';
                            document.getElementById('successMessage').style.display = 'block';
                            setTimeout(() => location.reload(), 3000);
                        } else {
                            showError(data.errors || 'Submission failed');
                        }
                    }).catch(err => {
                        spinner.classList.remove('show');
                        showError(err.message || 'Network error');
                    });
            });

            function showError(msg) {
                const e = document.getElementById('errorAlert');
                e.textContent = msg;
                e.style.display = 'block';
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }
        });
    </script>
</body>

</html>