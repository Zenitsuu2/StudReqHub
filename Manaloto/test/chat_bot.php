<?php
// index.php - Main chatbot interaction page

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set default language if not set
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = 'english'; // Default language
}

// Handle language toggle
if (isset($_GET['lang'])) {
    $_SESSION['language'] = strtolower($_GET['lang']) == 'tagalog' ? 'tagalog' : 'english';
}

$currentLanguage = $_SESSION['language'];

// Mock user data for testing if not set
if (!isset($_SESSION['user_data'])) {
    $_SESSION['user_data'] = [
        'firstname' => 'Juan',
        'middlename' => 'Dela',
        'lastname' => 'Cruz',
        'lrn' => '123456789012',
        'uli' => 'ULI-987654321',
        'grade_level' => 'Grade 10',
        'email' => 'juan.delacruz@example.com',
    ];
}
?>

<!DOCTYPE html>
<html lang="<?php echo $currentLanguage == 'tagalog' ? 'tl' : 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentLanguage == 'tagalog' ? 'Paghingi ng Dokumento - Chatbot' : 'Document Request - Chatbot'; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .chat-container {
            max-width: 800px;
            margin: 2rem auto;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            background-color: #fff;
            overflow: hidden;
        }
        
        .chat-header {
            background-color: #4a6fdc;
            color: white;
            padding: 1.5rem;
            text-align: center;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .language-toggle {
            position: absolute;
            right: 15px;
            top: 15px;
        }
        
        .language-toggle button, .language-toggle a {
            background: transparent;
            border: 1px solid white;
            color: white;
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 0.8rem;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .language-toggle button:hover, .language-toggle a:hover {
            background-color: white;
            color: #4a6fdc;
        }
        
        .back-button {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: 1px solid white;
            color: white;
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 0.9rem;
            transition: all 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .back-button:hover {
            background-color: white;
            color: #4a6fdc;
        }
        
        .chat-body {
            height: 350px;
            overflow-y: auto;
            padding: 1rem;
            background-color: #f5f7fb;
        }
        
        .chat-message {
            margin-bottom: 1rem;
            display: flex;
            flex-wrap: wrap;
        }
        
        .user-message {
            justify-content: flex-end;
        }
        
        .bot-message {
            justify-content: flex-start;
        }
        
        .message-content {
            padding: 0.75rem 1rem;
            border-radius: 20px;
            max-width: 70%;
            word-wrap: break-word;
        }
        
        .user-message .message-content {
            background-color: #4a6fdc;
            color: white;
            border-top-right-radius: 5px;
        }
        
        .bot-message .message-content {
            background-color: #e9ecef;
            color: #212529;
            border-top-left-radius: 5px;
        }
        
        .suggested-questions {
            padding: 1rem;
            background-color: #fff;
            border-top: 1px solid #e9ecef;
        }
        
        .question-btn {
            margin: 0.5rem;
            padding: 0.75rem 1.25rem;
            border-radius: 20px;
            background-color: #f0f2f5;
            border: none;
            transition: all 0.3s;
            text-align: left;
            display: block;
            width: 100%;
            cursor: pointer;
        }
        
        .question-btn:hover {
            background-color: #e9ecef;
        }
        
        .input-group {
            padding: 1rem;
            background-color: #fff;
            border-top: 1px solid #e9ecef;
        }
        
        .form-control {
            border-radius: 20px;
            padding: 0.75rem 1.25rem;
            border: 1px solid #ced4da;
        }
        
        .btn-send {
            border-radius: 20px;
            padding: 0.75rem 1.5rem;
            background-color: #4a6fdc;
            color: white;
            border: none;
        }
        
        .btn-send:hover {
            background-color: #3a5fc9;
        }
        
        .bot-avatar, .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
        }
        
        .bot-avatar {
            background-color: #4a6fdc;
            color: white;
        }
        
        .user-avatar {
            background-color: #6c757d;
            color: white;
        }
        
        /* Custom Sweet Alert Styling */
        .swal2-popup {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            border-radius: 15px;
        }
        
        .swal2-title {
            font-size: 1.5rem;
            color: #4a6fdc;
        }
        
        .swal2-content {
            font-size: 1rem;
        }
        
        .swal2-confirm {
            background-color: #4a6fdc !important;
            border-radius: 20px !important;
            padding: 0.75rem 2rem !important;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .chat-container {
                margin: 1rem;
                max-width: 100%;
            }
            
            .chat-header h2 {
                font-size: 1.2rem;
            }
            
            .back-button, .language-toggle button, .language-toggle a {
                padding: 3px 10px;
                font-size: 0.7rem;
            }
            
            .message-content {
                max-width: 85%;
            }
            
            .bot-avatar, .user-avatar {
                width: 30px;
                height: 30px;
                margin: 0 5px;
            }
            
            .suggested-questions h5 {
                font-size: 1rem;
            }
            
            .question-btn {
                padding: 0.5rem 1rem;
                margin: 0.3rem;
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 576px) {
            .chat-header {
                padding: 1rem;
                flex-direction: column;
            }
            
            .back-button {
                position: static;
                transform: none;
                margin-bottom: 0.5rem;
                font-size: 0.8rem;
            }
            
            .language-toggle {
                position: static;
                margin-top: 0.5rem;
            }
            
            .chat-header h2 {
                font-size: 1rem;
                margin: 0.5rem 0;
            }
            
            .chat-body {
                height: 300px;
            }
            
            .message-content {
                max-width: 90%;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid px-0">
        <div class="chat-container">
            <div class="chat-header">
                <a href="home.php" class="back-button">
                    <i class="fas fa-arrow-left"></i>
                    <?php echo $currentLanguage == 'tagalog' ? 'Bumalik' : 'Back'; ?>
                </a>
                <h2>
                    <i class="fas fa-comments"></i>
                    <?php echo $currentLanguage == 'tagalog' ? 'Paghingi ng Dokumento Assistant' : 'Document Request Assistant'; ?>
                </h2>
                <div class="language-toggle">
                    <a href="?lang=<?php echo $currentLanguage == 'tagalog' ? 'english' : 'tagalog'; ?>">
                        <?php echo $currentLanguage == 'tagalog' ? 'English' : 'Tagalog'; ?>
                    </a>
                </div>
            </div>
            
            <div class="chat-body" id="chatBody">
                <!-- Initial bot message -->
                <div class="chat-message bot-message">
                    <div class="bot-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="message-content">
                        <?php if ($currentLanguage == 'tagalog'): ?>
                            Kamusta! Ako ay ang iyong Document Request Assistant. Paano kita matutulungan ngayon?
                        <?php else: ?>
                            Hello! I'm your Document Request Assistant. How can I help you today?
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="suggested-questions">
                <h5><?php echo $currentLanguage == 'tagalog' ? 'Mga Iminumungkahing Tanong:' : 'Suggested Questions:'; ?></h5>
                
                <button class="question-btn" data-question="how_to_request">
                    <i class="fas fa-file-alt"></i> 
                    <?php echo $currentLanguage == 'tagalog' ? 'Paano mag-request ng dokumento?' : 'How to request a document?'; ?>
                </button>
                
                <button class="question-btn" data-question="available_documents">
                    <i class="fas fa-list"></i> 
                    <?php echo $currentLanguage == 'tagalog' ? 'Anong mga dokumento ang available?' : 'What documents are available?'; ?>
                </button>
                
                <button class="question-btn" data-question="track_status">
                    <i class="fas fa-search"></i> 
                    <?php echo $currentLanguage == 'tagalog' ? 'Paano i-track ang status ng aking request?' : 'How can I track my request status?'; ?>
                </button>
                
                <button class="question-btn" data-question="processing_time">
                    <i class="fas fa-clock"></i> 
                    <?php echo $currentLanguage == 'tagalog' ? 'Gaano katagal ang proseso?' : 'How long is the processing time?'; ?>
                </button>

                <button class="question-btn" data-question="personal_info">
                    <i class="fas fa-user"></i> 
                    <?php echo $currentLanguage == 'tagalog' ? 'Ano ang aking personal na impormasyon?' : 'What is my personal information?'; ?>
                </button>
                
                <button class="question-btn" data-question="firstname">
                    <i class="fas fa-id-card"></i> 
                    <?php echo $currentLanguage == 'tagalog' ? 'Ano ang aking first name?' : 'What is my first name?'; ?>
                </button>
            </div>
            
            <div class="input-group">
                <input type="text" id="userInput" class="form-control" placeholder="<?php echo $currentLanguage == 'tagalog' ? 'I-type ang iyong mensahe dito...' : 'Type your message here...'; ?>">
                <button id="sendBtn" class="btn btn-send ms-2">
                    <i class="fas fa-paper-plane"></i>
                    <?php echo $currentLanguage == 'tagalog' ? 'Ipadala' : 'Send'; ?>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    
    <script>
        // Get current language from PHP session
        const currentLanguage = "<?php echo $currentLanguage; ?>";
        
        // User data from the session
        const userData = {
            firstname: "<?php echo $_SESSION['user_data']['firstname']; ?>",
            middlename: "<?php echo $_SESSION['user_data']['middlename']; ?>",
            lastname: "<?php echo $_SESSION['user_data']['lastname']; ?>",
            fullname: "<?php echo $_SESSION['user_data']['firstname'] . ' ' . $_SESSION['user_data']['middlename'] . ' ' . $_SESSION['user_data']['lastname']; ?>",
            lrn: "<?php echo $_SESSION['user_data']['lrn']; ?>",
            uli: "<?php echo $_SESSION['user_data']['uli']; ?>",
            grade_level: "<?php echo $_SESSION['user_data']['grade_level']; ?>",
            email: "<?php echo $_SESSION['user_data']['email']; ?>"
        };
        
        // Responses object with both English and Tagalog versions
        const responses = {
            how_to_request: {
                english: {
                    title: "How to Request a Document",
                    html: `
                        <div style="text-align: left;">
                            <p><strong>Follow these steps to request a document:</strong></p>
                            <ol>
                                <li>Log into your account</li>
                                <li>Navigate to the "Document Requests" at the top  section</li>
                                <li>Select the document type you need:
                                    <ul>
                                        <li>Good Moral Certificate</li>
                                        <li>Diploma</li>
                                        <li>Certificate of Completion</li>
                                        <li>Certificate of Enrollment</li>
                                    </ul>
                                </li>
                                <li>Fill out the required information</li>
                                <li>Upload any necessary supporting documents</li>
                                <li>Submit your request</li>
                                <li>Take note of your request reference number</li>
                            </ol>
                            <p>Your request will be processed and you will be notified when it's ready for pickup.</p>
                        </div>
                    `
                },
                tagalog: {
                    title: "Paano Humiling ng Dokumento",
                    html: `
                        <div style="text-align: left;">
                            <p><strong>Sundin ang mga hakbang na ito para humiling ng dokumento:</strong></p>
                            <ol>
                                <li>Mag-login sa iyong account</li>
                                <li>Pumunta sa "Document Requests" na makikita sa taas o sa iyong harapan section</li>
                                <li>Piliin ang uri ng dokumento na kailangan mo:
                                    <ul>
                                        <li>Good Moral Certificate</li>
                                        <li>Diploma</li>
                                        <li>Certificate of Completion</li>
                                        <li>Certificate of Enrollment</li>
                                    </ul>
                                </li>
                                <li>Punan ang mga kinakailangang impormasyon</li>
                                <li>Mag-upload ng anumang kailangang suportang dokumento</li>
                                <li>I-submit ang iyong kahilingan</li>
                                <li>Tandaan ang iyong request reference number</li>
                            </ol>
                            <p>Ang iyong kahilingan ay ipoproseso at aabisuhan ka kapag ito ay handa nang kunin.</p>
                        </div>
                    `
                }
            },
            available_documents: {
                english: {
                    title: "Available Documents",
                    html: `
                        <div style="text-align: left;">
                            <p><strong>The following documents are available for request:</strong></p>
                            <ul>
                                <li><strong>Academic Documents:</strong>
                                    <ul>
                                        <li>Diploma</li>
                                        <li>Certificate of Grades</li>
                                    </ul>
                                </li>
                                <li><strong>Certificates:</strong>
                                    <ul>
                                        <li>Good Moral Character</li>
                                        <li>Certificate of Completion</li>
                                        <li>Certificate of Enrollment</li>
                                        <li>Certificate of Graduation</li>
                                    </ul>
                                </li>
                               
                            </ul>
                        </div>
                    `
                },
                tagalog: {
                    title: "Mga Available na Dokumento",
                    html: `
                        <div style="text-align: left;">
                            <p><strong>Ang mga sumusunod na dokumento ay pwedeng hilingin:</strong></p>
                            <ul>
                                <li><strong>Mga Dokumento sa Akademiko:</strong>
                                    <ul>
                                        <li>Diploma</li>
                                        <li>Certificate of Grades</li>
                                    </ul>
                                </li>
                                <li><strong>Mga Sertipiko:</strong>
                                    <ul>
                                        <li>Good Moral Character</li>
                                        <li>Certificate of Completion</li>
                                        <li>Certificate of Enrollment</li>
                                        <li>Certificate of Graduation</li>
                                    </ul>
                                </li>
                               
                            </ul>
                        </div>
                    `
                }
            },
            track_status: {
                english: {
                    title: "Track Your Request Status",
                    html: `
                        <div style="text-align: left;">
                            <p><strong>To track the status of your document request:</strong></p>
                            <ol>
                                <li>Log into your account</li>
                                <li>Go to "My Requests" section</li>
                                <li>Find your request in the list</li>
                                <li>Check the current status:
                                    <ul>
                                        <li><span style="color: ">Pending</span> - Request received but not yet processed</li>
                                        <li><span style="color: ">Processing</span> - Request is being worked on</li>
                                        <li><span style="color: ">Ready for Pickup</span> - Document is ready to be collected</li>
                                        <li><span style="color: ">Completed</span> - Document has been Completed</li>
                                    </ul>
                                </li>
                            </ol>
                            <p>You will also receive email notifications when there are updates to your request status.</p>
                        </div>
                    `
                },
                tagalog: {
                    title: "I-track ang Status ng Iyong Request",
                    html: `
                        <div style="text-align: left;">
                            <p><strong>Para i-track ang status ng iyong kahilingan ng dokumento:</strong></p>
                            <ol>
                                <li>Mag-login sa iyong account</li>
                                <li>Pumunta sa "My Requests" section</li>
                                <li>Hanapin ang iyong request sa listahan</li>
                                <li>Tingnan ang kasalukuyang status:
                                    <ul>
                                        <li><span style="color: ">Pending</span> - Natanggap na ang request pero hindi pa napoproseso</li>
                                        <li><span style="color: ">Processing</span> - Kasalukuyang pinoproseso ang request</li>
                                        <li><span style="color: ">Ready for Pickup</span> - Handa na ang dokumento para kunin</li>
                                        <li><span style="color: ">Completed</span> - Na-tapos na iyong dokumento</li>
                                    </ul>
                                </li>
                            </ol>
                            <p>Makakatanggap ka rin ng email notifications kapag may mga update sa status ng iyong request.</p>
                        </div>
                    `
                }
            },
            processing_time: {
                english: {
                    title: "Document Processing Time",
                    html: `
                        <div style="text-align: left;">
                            <p><strong>Standard processing times for document requests:</strong></p>
                            <ul>
                                <li><strong>Good Moral Certificate:</strong> 1-3 working days</li>
                                <li><strong>Diploma:</strong> 1-5 working days</li>
                                <li><strong>Certificate of Completion:</strong> 2-4 working days</li>
                                <li><strong>Certficate Of Enrollment:</strong> 1-6 working days</li>
                            </ul>
                            <p><em>Note: Processing times may vary during peak periods such as graduation season.</em></p>
                            <p>You will receive a notification when your document is ready for pickup.</p>
                        </div>
                    `
                },
                tagalog: {
                    title: "Tagal ng Pagproseso ng Dokumento",
                    html: `
                        <div style="text-align: left;">
                            <p><strong>Karaniwang tagal ng pagproseso para sa mga hiling ng dokumento:</strong></p>
                            <ul>
                                <li><strong>Good Moral Certificate:</strong> 1-3 araw ng trabaho</li>
                                <li><strong>Diploma:</strong> 1-5 araw ng trabaho</li>
                                <li><strong>Certificate of Completion:</strong> 2-4 araw ng trabaho</li>
                                <li><strong>Certificate of Enrollment:</strong> 1-6 araw ng trabaho</li>
                            </ul>
                            <p><em>Tandaan: Ang tagal ng pagproseso ay maaaring mag-iba sa mga peak periods tulad ng graduation season.</em></p>
                            <p>Makakatanggap ka ng abiso kapag ang iyong dokumento ay handa na para kunin.</p>
                        </div>
                    `
                }
            },
            personal_info: {
                english: {
                    title: "Your Personal Information",
                    html: `
                        <div style="text-align: left;">
                            <p><strong>Your Information:</strong></p>
                            <ul class="list-group">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <strong>Name:</strong> ${userData.fullname}
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <strong>LRN:</strong> ${userData.lrn}
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <strong>ULI:</strong> ${userData.uli}
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <strong>Grade Level:</strong> ${userData.grade_level}
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <strong>Email:</strong> ${userData.email}
                                </li>
                            </ul>
                            <p class="mt-3"><em>Note: Please keep your LRN and ULI private and secure.</em></p>
                        </div>
                    `
                },
                tagalog: {
                    title: "Iyong Personal na Impormasyon",
                    html: `
                        <div style="text-align: left;">
                            <p><strong>Iyong Impormasyon:</strong></p>
                            <ul class="list-group">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <strong>Pangalan:</strong> ${userData.fullname}
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <strong>LRN:</strong> ${userData.lrn}
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <strong>ULI:</strong> ${userData.uli}
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <strong>Baitang:</strong> ${userData.grade_level}
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <strong>Email:</strong> ${userData.email}
                                </li>
                            </ul>
                            <p class="mt-3"><em>Paalala: Panatilihing pribado at secure ang iyong LRN at ULI.</em></p>
                        </div>
                    `
                }
            }
        };
        
        // Improved Natural Language Processing (keyword matching with better detection)
        function processUserQuery(query) {
            query = query.toLowerCase().trim();
            
            // Check for name-related queries with more specific pattern matching
            if ((query.includes('first') && query.includes('name')) || 
                query.includes('firstname') || 
                (query.includes('ano') && query.includes('first name')) ||
                (query.includes('what') && query.includes('first name')) ||
                query.includes('pangalan') && query.includes('una')) {
                return { type: 'name', field: 'firstname' };
            } 
            else if ((query.includes('middle') && query.includes('name')) || 
                     query.includes('middlename') || 
                     (query.includes('ano') && query.includes('middle name')) ||
                     (query.includes('what') && query.includes('middle name')) ||
                     (query.includes('pangalan') && query.includes('gitnang'))) {
                return { type: 'name', field: 'middlename' };
            }
            else if ((query.includes('last') && query.includes('name')) || 
                     query.includes('lastname') || 
                     query.includes('surname') || 
                     (query.includes('ano') && query.includes('last name')) ||
                     (query.includes('what') && query.includes('last name')) ||
                     (query.includes('pangalan') && query.includes('apelyido'))) {
                return { type: 'name', field: 'lastname' };
            }
            else if ((query.includes('name') && !query.includes('first') && !query.includes('middle') && !query.includes('last')) ||
                     (query.includes('full') && query.includes('name')) ||
                     (query.includes('pangalan') && !query.includes('una') && !query.includes('gitnang') && !query.includes('apelyido')) ||
                     (query.includes('buong') && query.includes('pangalan'))) {
                return { type: 'name', field: 'fullname' };
            }
            else if (query.includes('lrn') || 
                    (query.includes('learner') && query.includes('reference')) || 
                    (query.includes('ano') && query.includes('lrn')) ||
                    (query.includes('what') && query.includes('lrn')) ||
                    query.includes('numero') && query.includes('mag-aaral')) {
                return { type: 'info', field: 'lrn' };
            }
            else if (query.includes('uli') || 
                    (query.includes('unique') && query.includes('learner')) || 
                    (query.includes('ano') && query.includes('uli')) ||
                    (query.includes('what') && query.includes('uli')) ||
                    query.includes('numero') && query.includes('natatanging')) {
                return { type: 'info', field: 'uli' };
            }
            else if ((query.includes('grade') && query.includes('level')) || 
                    query.includes('baitang') || 
                    (query.includes('ano') && query.includes('grade level')) ||
                    (query.includes('what') && query.includes('grade level')) ||
                    query.includes('antas') && query.includes('pag-aaral')) {
                return { type: 'info', field: 'grade_level' };
            }
            else if (query.includes('email') || 
                    (query.includes('e-mail')) || 
                    (query.includes('ano') && query.includes('email')) ||
                    (query.includes('what') && query.includes('email')) ||
                    query.includes('email') && query.includes('address')) {
                return { type: 'info', field: 'email' };
            }
            else if (query.includes('how') && query.includes('request') || 
                    query.includes('paano') && query.includes('humingi')) {
                return { type: 'response', key: 'how_to_request' };
            }
            else if (query.includes('available') && query.includes('document') || 
                    query.includes('anong') && query.includes('dokumento')) {
                return { type: 'response', key: 'available_documents' };
            }
            else if ((query.includes('track') || query.includes('status')) || 
                    (query.includes('subaybayan') || query.includes('katayuan'))) {
                return { type: 'response', key: 'track_status' };
            }
            else if ((query.includes('how long') || query.includes('processing time')) || 
                    (query.includes('gaano') || query.includes('katagal'))) {
                return { type: 'response', key: 'processing_time' };
            }
            else if ((query.includes('personal') && query.includes('information')) || 
                    (query.includes('personal') && query.includes('impormasyon'))) {
                return { type: 'response', key: 'personal_info' };
            }
            else {
                return { type: 'unknown' };
            }
        }

        // Function to add a message to the chat
        function addMessage(message, isUser = false) {
            const chatBody = document.getElementById('chatBody');
            const messageDiv = document.createElement('div');
            messageDiv.className = `chat-message ${isUser ? 'user-message' : 'bot-message'}`;
            
            const avatarDiv = document.createElement('div');
            avatarDiv.className = isUser ? 'user-avatar' : 'bot-avatar';
            avatarDiv.innerHTML = `<i class="fas ${isUser ? 'fa-user' : 'fa-robot'}"></i>`;
            
            const contentDiv = document.createElement('div');
            contentDiv.className = 'message-content';
            contentDiv.textContent = message;
            
            messageDiv.appendChild(avatarDiv);
            messageDiv.appendChild(contentDiv);
            chatBody.appendChild(messageDiv);
            
            // Scroll to bottom
            chatBody.scrollTop = chatBody.scrollHeight;
        }

        // Function to handle user input
        function handleUserInput() {
            const userInput = document.getElementById('userInput');
            const query = userInput.value.trim();
            
            if (query === '') return;
            
            // Add user message to chat
            addMessage(query, true);
            
            // Process the query
            const result = processUserQuery(query);
            
            if (result.type === 'name' || result.type === 'info') {
                // Handle personal information queries
                const value = userData[result.field];
                const response = currentLanguage === 'tagalog' ? 
                    `Ang iyong ${result.field.replace('_', ' ')} ay: ${value}` :
                    `Your ${result.field.replace('_', ' ')} is: ${value}`;
                
                addMessage(response);
            } 
            else if (result.type === 'response') {
                // Handle predefined responses
                const response = responses[result.key][currentLanguage];
                Swal.fire({
                    title: response.title,
                    html: response.html,
                    confirmButtonText: currentLanguage === 'tagalog' ? 'Salamat' : 'Thank you'
                });
                
                const simpleResponse = currentLanguage === 'tagalog' ?
                    'Narito ang impormasyon na iyong hinihingi:' :
                    'Here is the information you requested:';
                
                addMessage(simpleResponse);
            } 
            else {
                // Handle unknown queries
                const response = currentLanguage === 'tagalog' ?
                    'Paumanhin, hindi ko maintindihan ang iyong tanong. Maaari mo bang linawin ito?' :
                    "I'm sorry, I didn't understand your question. Could you please clarify?";
                
                addMessage(response);
            }
            
            // Clear input
            userInput.value = '';
        }

        // Event listeners
        document.getElementById('sendBtn').addEventListener('click', handleUserInput);
        document.getElementById('userInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                handleUserInput();
            }
        });

        // Suggested questions click handlers
        document.querySelectorAll('.question-btn').forEach(button => {
            button.addEventListener('click', function() {
                const questionType = this.getAttribute('data-question');
                
                // Special handling for firstname question
                if (questionType === 'firstname') {
                    const response = currentLanguage === 'tagalog' ? 
                        `Ang iyong first name ay: ${userData.firstname}` :
                        `Your first name is: ${userData.firstname}`;
                    
                    addMessage(this.textContent.trim(), true);
                    addMessage(response);
                    return;
                }
                
                // Handle other suggested questions
                const response = responses[questionType][currentLanguage];
                Swal.fire({
                    title: response.title,
                    html: response.html,
                    confirmButtonText: currentLanguage === 'tagalog' ? 'Salamat' : 'Thank you'
                });
                
                addMessage(this.textContent.trim(), true);
                addMessage(currentLanguage === 'tagalog' ?
                    'Narito ang impormasyon na iyong hinihingi:' :
                    'Here is the information you requested:');
            });
        });

        // Initial greeting based on time of day
        function getTimeBasedGreeting() {
            const hour = new Date().getHours();
            if (currentLanguage === 'tagalog') {
                if (hour < 12) return 'Magandang umaga!';
                if (hour < 18) return 'Magandang hapon!';
                return 'Magandang gabi!';
            } else {
                if (hour < 12) return 'Good morning!';
                if (hour < 18) return 'Good afternoon!';
                return 'Good evening!';
            }
        }

        // Show initial greeting after a short delay
        setTimeout(() => {
            const greeting = getTimeBasedGreeting();
            const message = currentLanguage === 'tagalog' ?
                `${greeting} Ako ay ang iyong Document Request Assistant. Paano kita matutulungan ngayon?` :
                `${greeting} I'm your Document Request Assistant. How can I help you today?`;
            
            addMessage(message);
        }, 1000);
    </script>
</body>
</html>