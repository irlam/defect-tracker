<?php
// add_contractor_readme.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Contractor - Construction Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
            background-color: #f8f9fa;
        }

        .card {
            box-shadow: 0 2px 4px rgba(0,0,0,.05);
            border: none;
            margin-bottom: 1rem;
        }

        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0,0,0,.05);
            padding: 1rem;
        }

        .card-body {
            padding: 1.25rem;
        }

        .form-label {
            font-weight: 500;
            color: #2c3e50;
        }

        .form-control:focus, .form-select:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }

        .required-field::after {
            content: " *";
            color: #dc3545;
        }

        @media (max-width: 991.98px) {
            .main-content {
                margin-left: 0;
                padding-top: 60px;
            }
        }

        /* Form validation styles */
        .was-validated .form-control:invalid:focus,
        .form-control.is-invalid:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220,53,69,.25);
        }

        .was-validated .form-control:valid:focus,
        .form-control.is-valid:focus {
            border-color: #198754;
            box-shadow: 0 0 0 0.2rem rgba(25,135,84,.25);
        }
    </style>
</head>
<body>
    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Add Contractor - Construction Defect Tracker</h1>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Instructions</h5>
            </div>
            <div class="card-body">
                <p>This webpage allows the user to add new contractors to the Construction Defect Tracker system. Users must be logged in to access this page. The form collects various details about the contractor, including company information, contact details, address, and additional notes. The webpage also performs validation to ensure the data entered is correct.</p>
                
                <h5>Accessing the Page</h5>
                <ul>
                    <li><strong>Login</strong>: Ensure you are logged in. If not, you will be redirected to the login page.</li>
                    <li><strong>Navigate</strong>: Once logged in, navigate to the <code>add_contractor.php</code> page to add a new contractor.</li>
                </ul>

                <h5>Filling Out the Form</h5>
                <ul>
                    <li><strong>Company Information</strong>:
                        <ul>
                            <li><strong>Company Name</strong>: Enter the name of the contractor's company. This field is required.</li>
                            <li><strong>VAT Number</strong>: (Optional) Enter the VAT number if available.</li>
                            <li><strong>Company Number</strong>: (Optional) Enter the company number if available.</li>
                            <li><strong>UTR Number</strong>: (Optional) Enter the Unique Taxpayer Reference (UTR) number if available.</li>
                            <li><strong>Trade/Specialty</strong>: Select the contractor's trade from the dropdown list. This field is required.</li>
                        </ul>
                    </li>
                    <li><strong>Contact Information</strong>:
                        <ul>
                            <li><strong>Contact Name</strong>: Enter the name of the primary contact person. This field is required.</li>
                            <li><strong>Email Address</strong>: Enter a valid email address for the contact person. This field is required.</li>
                            <li><strong>Phone Number</strong>: Enter a valid UK phone number for the contact person. This field is required.</li>
                        </ul>
                    </li>
                    <li><strong>Address Information</strong>:
                        <ul>
                            <li><strong>Address Line 1</strong>: Enter the first line of the contractor's address.</li>
                            <li><strong>Address Line 2</strong>: (Optional) Enter the second line of the contractor's address.</li>
                            <li><strong>City/Town</strong>: Enter the city or town of the contractor's address.</li>
                            <li><strong>County</strong>: Select the county from the dropdown list.</li>
                            <li><strong>Postcode</strong>: Enter a valid UK postcode.</li>
                        </ul>
                    </li>
                    <li><strong>Additional Information</strong>:
                        <ul>
                            <li><strong>Insurance Information</strong>: (Optional) Enter any relevant insurance information.</li>
                            <li><strong>Notes</strong>: (Optional) Enter any additional notes about the contractor.</li>
                        </ul>
                    </li>
                </ul>

                <h5>Form Validation</h5>
                <p>The form uses client-side validation to ensure required fields are filled out and correctly formatted. If a required field is missing or incorrectly formatted, an error message will be displayed. Email and phone number fields have specific validation to ensure they are correctly formatted.</p>

                <h5>Submitting the Form</h5>
                <p>Once all required fields are filled out correctly, click the <strong>Add Contractor</strong> button to submit the form. If the submission is successful, a success message will be displayed, and you will have the option to return to the contractor list. If there are any errors during submission, an error message will be displayed.</p>

                <h5>Navigation</h5>
                <p>To go back to the contractors list, click the <strong>Back to Contractors</strong> button at the top of the page or the link in the success message.</p>

                <h5>Additional Features</h5>
                <ul>
                    <li><strong>Navbar</strong>: The page includes a navigation bar for easy access to other sections of the system.</li>
                    <li><strong>Form Styling</strong>: The form is styled with Bootstrap for a consistent and responsive design.</li>
                    <li><strong>Error Logging</strong>: Errors are logged to a file for debugging purposes.</li>
                </ul>

                <h5>Notes</h5>
                <p>Ensure that all required fields are filled out correctly before submitting the form. Use the provided validation messages to correct any errors before submission. Contact your system administrator if you encounter any issues while using this page.</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>