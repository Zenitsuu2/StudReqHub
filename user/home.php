<?php
    // Define site title and other dynamic values
    $school_name = "VILLA TEODORA ELEMENTARY SCHOOL";
    $site_title = "EDU.REQUEST HUB";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Request Hub</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        body {
            background: linear-gradient(to right, #003366, #0099cc);
            color: white;
            text-align: center;
            padding: 20px;
        }
        .container {
            max-width: 1100px;
            margin: auto;
            padding: 20px;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.3);
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .logo img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: white;
            padding: 5px;
        }
        .title {
            text-align: left;
        }
        .title h1 {
            font-size: 26px;
            font-weight: bold;
        }
        .title p {
            font-size: 14px;
            letter-spacing: 2px;
            color: #f0f0f0;
        }
        .nav {
            display: flex;
            gap: 20px;
        }
        .nav a {
            color: white;
            text-decoration: none;
            font-weight: bold;
            padding: 10px 15px;
            border-radius: 5px;
            transition: 0.3s;
        }
        .nav a:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        .buttons {
            display: flex;
            justify-content: center;
            gap: 25px;
            margin-top: 40px;
            flex-wrap: wrap;
        }
        .button {
            background: white;
            color: #003366;
            padding: 20px;
            width: 200px;
            border-radius: 15px;
            text-align: center;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            transition: transform 0.3s, box-shadow 0.3s;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .button:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.3);
        }
        .button img {
            width: 50px;
            height: 50px;
            margin-bottom: 10px;
        }
        .images {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }
        .image-box {
            background: white;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: black;
            font-weight: bold;
            font-size: 20px;
            padding: 40px;
            height: 200px;
            overflow: hidden;
        }
        .image-box img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
            border-radius: 10px;
        }
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            .nav {
                flex-direction: column;
                align-items: center;
                gap: 10px;
                margin-top: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">
                <img src="pic/logo.png" alt="School Logo">
                <div class="title">
                    <h1><?php echo $school_name; ?></h1>
                    <p><?php echo $site_title; ?></p>
                </div>
            </div>
            <div class="nav">
                <a href="#">Home</a>
                <a href="../viewing page 1/index.html">About us</a>
                <a href="events.php">Events</a>
                <a href="Loginpage.php">Logout</a>
            </div>
        </div>
        <div class="buttons">
            <a href="dashboard_user.php?step=request_form" class="button">
                <img src="pic/req img.png" alt="Request Form">
                <p>Request Form</p>
            </a>
            <a href="dashboard_user.php?step=request_form" class="button">
                <img src="pic/track.jfif" alt="Track Request">
                <p>Track Request</p>
            </a>
            <a href="#" class="button">
                <img src="pic/images.png" alt="Request History">
                <p>Request History</p>
            </a>
        </div>
        <div class="images">
            <div class="image-box">
                <img src="pic/9-removebg-preview.png" alt="Image 1" style="width:100%; height:auto; border-radius: 10px;">
            </div>
            <div class="image-box">
                <img src="pic/8-removebg-preview.png" alt="Image 2" style="width:100%; height:auto; border-radius: 10px;">
            </div>
        </div>        

        <!-- Request Document Modal -->
        <div class="modal fade" id="requestDocumentModal" tabindex="-1" aria-labelledby="requestDocumentModalLabel" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <form id="requestForm" method="POST" action="submit_request.php">
                <div class="modal-header">
                  <h5 class="modal-title" id="requestDocumentModalLabel">Request Document</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <!-- Example Input Fields -->
                  <div class="mb-3">
                    <label for="documentType" class="form-label">Document Type</label>
                    <select name="document_type" id="documentType" class="form-select" required>
                      <option value="">Select Document Type</option>
                      <option value="Certificate of Enrollment">Certificate of Enrollment</option>
                      <option value="Good Moral Certificate">Good Moral Certificate</option>
                      <option value="Diploma">Diploma</option>
                      <option value="Certificate of Completion of Kinder">Certificate of Completion of Kinder</option>
                    </select>
                  </div>
                  <!-- Purpose (you may later swap to dropdown when Certificate of Enrollment is selected) -->
                  <div class="mb-3" id="purposeContainer">
                    <label for="purposeInput" class="form-label">Purpose</label>
                    <input type="text" name="purpose" id="purposeInput" class="form-control" placeholder="Enter Purpose" required>
                  </div>
                  <div class="mb-3">
                    <label for="schoolYear" class="form-label">School Year</label>
                    <input type="text" name="school_year" id="schoolYear" class="form-control" placeholder="e.g. 2023-2024" required>
                  </div>
                  <div class="mb-3">
                    <label for="priority" class="form-label">Priority</label>
                    <select name="priority" id="priority" class="form-select" required>
                      <option value="">Select Priority</option>
                      <option value="Normal">Normal</option>
                      <option value="Urgent">Urgent</option>
                    </select>
                  </div>
                  <!-- You can add more fields as needed -->
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                  <!-- Instead of submitting, review the input first -->
                  <button type="button" id="reviewRequestBtn" class="btn btn-primary">Review Request</button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Review Request Modal -->
        <div class="modal fade" id="reviewRequestModal" tabindex="-1" aria-labelledby="reviewRequestModalLabel" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="reviewRequestModalLabel">Review Your Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <p><strong>Document Type:</strong> <span id="reviewDocumentType"></span></p>
                <p><strong>Purpose:</strong> <span id="reviewPurpose"></span></p>
                <p><strong>School Year:</strong> <span id="reviewSchoolYear"></span></p>
                <p><strong>Priority:</strong> <span id="reviewPriority"></span></p>
                <!-- Add more review fields if needed -->
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Edit</button>
                <button type="button" id="submitFinalRequest" class="btn btn-success">Submit Request</button>
              </div>
            </div>
          </div>
        </div>

        <!-- JavaScript to handle the review step -->
        <script>
        document.getElementById('reviewRequestBtn').addEventListener('click', function() {
          // Get values from the request form
          const documentType = document.getElementById('documentType').value;
          const purpose = document.getElementById('purposeInput').value;
          const schoolYear = document.getElementById('schoolYear').value;
          const priority = document.getElementById('priority').value;
          
          // Validate fields if needed
          if (!documentType || !purpose || !schoolYear || !priority) {
            alert("Please fill in all fields.");
            return;
          }
          
          // Populate review modal spans with the input values
          document.getElementById('reviewDocumentType').textContent = documentType;
          document.getElementById('reviewPurpose').textContent = purpose;
          document.getElementById('reviewSchoolYear').textContent = schoolYear;
          document.getElementById('reviewPriority').textContent = priority;
          
          // Close the request modal and open the review modal
          const requestModal = bootstrap.Modal.getInstance(document.getElementById('requestDocumentModal'));
          requestModal.hide();
          
          const reviewModal = new bootstrap.Modal(document.getElementById('reviewRequestModal'));
          reviewModal.show();
        });

        document.getElementById('submitFinalRequest').addEventListener('click', function() {
            const form = document.getElementById('requestForm');
            const formData = new FormData(form);

            // Submit the form via fetch() for AJAX processing
            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                 if (data.success) {
                     Swal.fire({
                         icon: 'success',
                         title: 'Request Submitted',
                         text: data.message || 'Your request has been submitted successfully!',
                         confirmButtonColor: '#003366'
                     }).then(() => {
                         window.location.reload();
                     });
                 } else {
                     Swal.fire({
                         icon: 'error',
                         title: 'Oops...',
                         text: data.message || 'There was an error processing your request. Please try again.',
                         confirmButtonColor: '#003366'
                     });
                 }
            })
            .catch(error => {
                 console.error('Error:', error);
                 Swal.fire({
                     icon: 'error',
                     title: 'Oops...',
                     text: 'There was an error processing your request. Please try again.',
                     confirmButtonColor: '#003366'
                 });
            });
        });
        </script>
</body>
</html>