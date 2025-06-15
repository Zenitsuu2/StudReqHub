<?php
session_start();
require_once '../Connection/database.php';
// Update the query
$query = "SELECT 
    u.id,
    u.lrn,
    u.uli,
    CONCAT(u.firstname, ' ', u.lastname) as full_name,
    u.dob,
    u.contact,
    u.address,
    u.email,
    u.grade_level,
    u.created_at,
    'offline' as online_status  /* Added default status */
    FROM users u 
    WHERE u.grade_level IS NOT NULL
    ORDER BY u.lastname, u.firstname";

$result = $conn->query($query);

// Debug the query result
if (!$result) {
    die("Query failed: " . $conn->error);
}
$studentData = array();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $studentData[] = array(
            'id' => $row['id'],
            'lrn' => $row['lrn'] ?? 'N/A',
            'uli' => $row['uli'] ?? 'N/A',
            'full_name' => $row['full_name'] ?? 'N/A',
            'dob' => $row['dob'] ?? 'N/A',
            'contact' => $row['contact'] ?? 'N/A',
            'address' => $row['address'] ?? 'N/A',
            'email' => $row['email'] ?? 'N/A',
            'grade_level' => $row['grade_level'] ?? 'N/A',
            'created_at' => $row['created_at'] ?? 'N/A',
            'online_status' => $row['online_status'] ?? 'offline'  /* Added status */
        );
    }
}

// Convert to JSON and add debug output
$studentDataJSON = json_encode($studentData);
echo "<!-- Debug: " . count($studentData) . " students loaded -->";
echo "<!-- Debug: SQL Query: " . $query . " -->";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Register</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <!-- Add Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Add Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        :root {
            --sidebar-width: 250px;
            --header-height: 60px;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            background-color: #f9fafb;
            transition: margin-left 0.3s ease;
            width: calc(100% - var(--sidebar-width));
            padding: 1.5rem;
        }

        /* Add these new styles to match sidebar spacing */
        .sidebar {
            background: var(--sidebar-bg, #1a1f36); /* This might override the shared sidebar styles */
        }

        /* Fix container spacing */
        .container {
            max-width: 100%;
            padding-right: 1rem;
            padding-left: 1rem;
        }

        /* Ensure content grid stays within bounds */
        .grid {
            margin: 0;
            width: 100%;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                width: 100%;
            }
        }

        .student-card {
            transition: all 0.3s ease;
            transform-origin: center;
            animation: fadeIn 0.5s ease-in-out;
        }
        
        .student-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .status-badge {
            transition: all 0.3s ease;
        }
        
        .status-badge.active {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 0.7; }
            50% { opacity: 1; }
            100% { opacity: 0.7; }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .search-highlight {
            background-color: #FEFCE8;
            padding: 2px;
            border-radius: 2px;
        }
        
        .loader {
            border-top-color: #3498db;
            animation: spinner 1.5s linear infinite;
        }
        
        @keyframes spinner {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Add these additional styles */
        .student-register-container {
            padding: 1.5rem;
            margin: -1.5rem;
            background-color: #f9fafb;
        }

        .content-wrapper {
            max-width: 1440px;
            margin: 0 auto;
        }

        /* Fix card grid spacing */
        #card-view {
            grid-gap: 1rem;
            padding: 0.5rem 0;
        }

        /* Ensure consistent table view spacing */
        #table-view {
            margin: 0 -1.5rem;
            padding: 0 1.5rem;
        }
        
        /* Avatar styles */
        .student-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .modal-avatar {
            width: 128px;
            height: 128px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        
        .table-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'sidebar.php'; ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <div class="flex h-screen">
        <div class="main-content">
            <div class="student-register-container">
                <div class="content-wrapper">
                    <div class="container mx-auto px-4 py-6">
                        <div class="flex flex-col md:flex-row items-center justify-between mb-6">
                            <h1 class="text-3xl font-bold text-gray-800 mb-4 md:mb-0">Student Register</h1>
                            <div class="flex flex-col md:flex-row w-full md:w-auto space-y-3 md:space-y-0 md:space-x-3">
                                <div class="relative">
                                    <input id="search" type="text" placeholder="Search students..." class="px-4 py-2 rounded-md border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 w-full">
                                    <span class="absolute right-3 top-2.5 text-gray-400">
                                        <i class="fas fa-search"></i>
                                    </span>
                                </div>
                                <select id="grade-filter" class="px-4 py-2 rounded-md border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="all">All Grades</option>
                                    <option value="0">Kindergarden</option>
                                    <option value="1">Grade 1</option>
                                    <option value="2">Grade 2</option>
                                    <option value="3">Grade 3</option>
                                    <option value="4">Grade 4</option>
                                    <option value="5">Grade 5</option>
                                    <option value="6">Grade 6</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex justify-between items-center mb-4">
                                <div class="text-sm text-gray-500">
                                    Showing <span id="student-count" class="font-medium">0</span> students
                                </div>
                                <div class="flex space-x-2">
                                    <button id="card-view-btn" class="px-3 py-1 rounded bg-blue-500 text-white hover:bg-blue-600 transition">
                                        <i class="fas fa-th-large mr-1"></i> Card View
                                    </button>
                                    <button id="table-view-btn" class="px-3 py-1 rounded bg-gray-200 text-gray-700 hover:bg-gray-300 transition">
                                        <i class="fas fa-list mr-1"></i> Table View
                                    </button>
                                </div>
                            </div>
                            
                            <div id="loader" class="flex justify-center items-center py-12">
                                <div class="loader rounded-full border-4 border-gray-200 h-12 w-12"></div>
                            </div>
                            
                            <!-- Card View -->
                            <div id="card-view" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                                <!-- Cards will be populated here -->
                            </div>
                            
                            <!-- Table View (Hidden by default) -->
                            <div id="table-view" class="overflow-x-auto hidden">
                                <table class="min-w-full bg-white">
                                    <thead>
                                        <tr class="bg-gray-100 text-gray-700 uppercase text-sm leading-normal">
                                            <th class="py-3 px-6 text-left">Student</th>
                                            <th class="py-3 px-6 text-left">LRN/ULI</th>
                                            <th class="py-3 px-6 text-left">Grade</th>
                                            <th class="py-3 px-6 text-left">Contact</th>
                                            <th class="py-3 px-6 text-center">Status</th>
                                          
                                        </tr>
                                    </thead>
                                    <tbody id="table-body">
                                        <!-- Table rows will be populated here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Pagination -->
                        <div class="mt-6 flex justify-center">
                            <div class="flex space-x-1">
                                <button id="prev-page" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Previous</button>
                                <div id="pagination-numbers" class="flex space-x-1">
                                    <!-- Page numbers will be populated here -->
                                </div>
                                <button id="next-page" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Next</button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Student Details Modal -->
                    <div id="student-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
                        <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl mx-4">
                            <div class="flex justify-between items-center border-b p-4">
                                <h3 class="text-xl font-bold text-gray-700">Student Details</h3>
                                <button id="close-modal" class="text-gray-500 hover:text-gray-700">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="p-6" id="modal-content">
                                <!-- Content will be dynamically inserted here -->
                            </div>
                            <div class="border-t p-4 flex justify-end">
                                <button id="close-modal-btn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <script>
                        // Initialize with the PHP data
                        const studentData = <?php echo $studentDataJSON; ?>;
                        console.log('Initial Student Data:', studentData);

                        // Variables for pagination
                        let currentPage = 1;
                        const itemsPerPage = 8;
                        let filteredData = [...studentData];
                        let currentView = 'card'; // Track current view

                        // Get placeholder avatar URL based on name
                        function getAvatarUrl(name) {
                            // Generate a unique but consistent color based on name
                            const hash = name.split('').reduce((acc, char) => {
                                return char.charCodeAt(0) + ((acc << 5) - acc);
                            }, 0);
                            
                            // Use a placeholder service or generate an SVG avatar
                            // You can replace this with a real avatar image path if available
                            return `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=random&color=fff&size=256`;
                        }

                        $(document).ready(function() {
                            // Show initial data
                            renderStudents();
                            updatePagination();
                            $('#student-count').text(studentData.length);
                            $('#loader').hide();
                            
                            // Event listeners for search and filter
                            $('#search').on('input', filterStudents);
                            $('#grade-filter').on('change', filterStudents);
                            
                            // View toggle buttons
                            $('#card-view-btn').on('click', function() {
                                currentView = 'card';
                                $('#card-view').removeClass('hidden');
                                $('#table-view').addClass('hidden');
                                $(this).removeClass('bg-gray-200 text-gray-700').addClass('bg-blue-500 text-white');
                                $('#table-view-btn').removeClass('bg-blue-500 text-white').addClass('bg-gray-200 text-gray-700');
                            });
                            
                            $('#table-view-btn').on('click', function() {
                                currentView = 'table';
                                $('#card-view').addClass('hidden');
                                $('#table-view').removeClass('hidden');
                                $(this).removeClass('bg-gray-200 text-gray-700').addClass('bg-blue-500 text-white');
                                $('#card-view-btn').removeClass('bg-blue-500 text-white').addClass('bg-gray-200 text-gray-700');
                            });
                            
                            // Pagination buttons
                            $('#prev-page').on('click', function() {
                                if (currentPage > 1) {
                                    currentPage--;
                                    renderStudents();
                                    updatePagination();
                                }
                            });
                            
                            $('#next-page').on('click', function() {
                                const totalPages = Math.ceil(filteredData.length / itemsPerPage);
                                if (currentPage < totalPages) {
                                    currentPage++;
                                    renderStudents();
                                    updatePagination();
                                }
                            });
                            
                            // Close modal buttons
                            $('#close-modal, #close-modal-btn').on('click', function() {
                                $('#student-modal').addClass('hidden');
                            });

                            // Use event delegation for view details buttons
                            $(document).on('click', '.view-details', function() {
                                const studentId = $(this).data('id');
                                showStudentDetails(studentId);
                            });
                        });

                        // Render students with pagination
                        function renderStudents() {
                            const startIndex = (currentPage - 1) * itemsPerPage;
                            const endIndex = startIndex + itemsPerPage;
                            const currentPageData = filteredData.slice(startIndex, endIndex);
                            
                            const cardView = $('#card-view');
                            const tableBody = $('#table-body');
                            
                            cardView.empty();
                            tableBody.empty();
                            
                            // Get search term for highlighting
                            const searchTerm = $('#search').val().toLowerCase();
                            
                            currentPageData.forEach(student => {
                                // Get avatar URL
                                const avatarUrl = getAvatarUrl(student.full_name);
                                
                                // Card View
                                const card = $(`
                                    <div class="student-card bg-white rounded-lg shadow overflow-hidden" style="animation-delay: ${Math.random() * 0.5}s">
                                        <div class="px-4 py-5 sm:px-6 flex justify-between items-center bg-gray-50">
                                            <div class="flex items-center">
                                                <img src="${avatarUrl}" alt="${student.full_name}" class="w-10 h-10 rounded-full mr-3">
                                                <div>
                                                    <h3 class="text-lg font-bold text-gray-800">${highlightText(student.full_name, searchTerm)}</h3>
                                                    <p class="text-sm text-gray-500">Grade ${student.grade_level}</p>
                                                </div>
                                            </div>
                                            <div class="flex items-center">
                                                <span class="status-badge ${student.online_status} px-3 py-1 rounded-full text-sm font-medium ${student.online_status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}">
                                                    <span class="inline-block h-2 w-2 rounded-full ${student.online_status === 'active' ? 'bg-green-500' : 'bg-gray-400'} mr-1"></span>
                                                    ${capitalizeFirstLetter(student.online_status || 'offline')}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
                                            <div class="grid grid-cols-1 gap-3">
                                                <div class="flex items-center">
                                                    <div class="min-w-0 flex-1">
                                                        <p class="text-sm text-gray-500">LRN: ${highlightText(student.lrn, searchTerm)}</p>
                                                        <p class="text-sm text-gray-500">ULI: ${highlightText(student.uli, searchTerm)}</p>
                                                        <p class="text-sm text-gray-500">Email: ${highlightText(student.email, searchTerm)}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="bg-gray-50 px-4 py-3 border-t flex justify-end">
                                            <button class="view-details px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded transition duration-200" data-id="${student.id}">
                                                View Details
                                            </button>
                                        </div>
                                    </div>
                                `);
                                
                                cardView.append(card);
                                
                                // Table View
                                const row = $(`
                                    <tr class="border-b hover:bg-gray-50 transition" style="animation-delay: ${Math.random() * 0.5}s">
                                        <td class="py-3 px-6 text-left whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="mr-2">
                                                    <img src="${avatarUrl}" class="table-avatar" alt="${student.full_name}">
                                                </div>
                                                <span class="font-medium">${highlightText(student.full_name, searchTerm)}</span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-6 text-left">
                                            <div>LRN: ${highlightText(student.lrn || 'N/A', searchTerm)}</div>
                                            <div class="text-xs text-gray-500">ULI: ${highlightText(student.uli || 'N/A', searchTerm)}</div>
                                        </td>
                                        <td class="py-3 px-6 text-left">
                                            Grade ${student.grade_level || 'N/A'}
                                        </td>
                                        <td class="py-3 px-6 text-left">
                                            <div>${student.contact || 'N/A'}</div>
                                            <div class="text-xs text-gray-500">${highlightText(student.email || 'N/A', searchTerm)}</div>
                                        </td>
                                        <td class="py-3 px-6 text-center">
                                            <span class="status-badge ${student.online_status} px-3 py-1 rounded-full text-xs font-medium ${student.online_status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}">
                                                <span class="inline-block h-2 w-2 rounded-full ${student.online_status === 'active' ? 'bg-green-500' : 'bg-gray-400'} mr-1"></span>
                                                ${capitalizeFirstLetter(student.online_status || 'offline')}
                                            </span>
                                        </td>
                                        <td class="py-3 px-6 text-center">
                                            <button class="view-details px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white text-xs rounded transition duration-200" data-id="${student.id}">
                                                Details
                                            </button>
                                        </td>
                                    </tr>
                                `);
                                
                                tableBody.append(row);
                            });
                        }
                        
                        // Filter students based on search and filters
                        function filterStudents() {
                            const searchTerm = $('#search').val().toLowerCase();
                            const gradeFilter = $('#grade-filter').val();
                            
                            filteredData = studentData.filter(student => {
                                const matchesSearch = 
                                    String(student.full_name).toLowerCase().includes(searchTerm) || 
                                    String(student.lrn).toLowerCase().includes(searchTerm) || 
                                    String(student.uli).toLowerCase().includes(searchTerm) || 
                                    String(student.email).toLowerCase().includes(searchTerm);
                                
                                const matchesGrade = gradeFilter === 'all' || String(student.grade_level) === gradeFilter;
                                
                                return matchesSearch && matchesGrade;
                            });
                            
                            currentPage = 1;
                            renderStudents();
                            updatePagination();
                            $('#student-count').text(filteredData.length);
                        }
                        
                        // Update pagination numbers
                        function updatePagination() {
                            const totalPages = Math.ceil(filteredData.length / itemsPerPage);
                            
                            const paginationContainer = $('#pagination-numbers');
                            paginationContainer.empty();
                            
                            const maxVisiblePages = 5;
                            let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
                            let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
                            
                            if (endPage - startPage + 1 < maxVisiblePages) {
                                startPage = Math.max(1, endPage - maxVisiblePages + 1);
                            }
                            
                            for (let i = startPage; i <= endPage; i++) {
                                const pageButton = $(`
                                    <button class="page-number px-4 py-2 ${i === currentPage ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700'} rounded-md hover:bg-gray-300">
                                        ${i}
                                    </button>
                                `);
                                
                                pageButton.on('click', function() {
                                    currentPage = i;
                                    renderStudents();
                                    updatePagination();
                                });
                                
                                paginationContainer.append(pageButton);
                            }
                            
                            // Disable/enable navigation buttons
                            $('#prev-page').prop('disabled', currentPage === 1).toggleClass('opacity-50', currentPage === 1);
                            $('#next-page').prop('disabled', currentPage === totalPages).toggleClass('opacity-50', currentPage === totalPages);
                        }
                        
                        // Show student details in modal
                        function showStudentDetails(studentId) {
                            console.log('Showing details for student:', studentId); // Debug line
                            // Convert to number to ensure proper comparison if IDs are stored as numbers
                            const id = parseInt(studentId);
                            // Find the student by ID
                            const student = studentData.find(s => parseInt(s.id) === id);
                            
                            if (student) {
                                // Get avatar URL for modal
                                const avatarUrl = getAvatarUrl(student.full_name);
                                
                                $('#modal-content').html(`
                                    <div class="flex flex-col md:flex-row">
                                        <div class="md:w-1/3 flex flex-col items-center">
                                            <img src="${avatarUrl}" alt="${student.full_name}" class="modal-avatar mb-4">
                                            <h4 class="text-xl font-bold text-center">${student.full_name}</h4>
                                            <p class="text-sm text-gray-500 mb-4">Grade ${student.grade_level}</p>
                                            <span class="status-badge ${student.online_status} px-3 py-1 rounded-full text-sm font-medium ${student.online_status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'} mb-4">
                                                <span class="inline-block h-2 w-2 rounded-full ${student.online_status === 'active' ? 'bg-green-500' : 'bg-gray-400'} mr-1"></span>
                                                ${capitalizeFirstLetter(student.online_status || 'offline')}
                                            </span>
                                        </div>
                                        <div class="md:w-2/3 mt-6 md:mt-0 md:pl-6">
                                            <div class="grid grid-cols-1 gap-4">
                                                <div>
                                                    <h5 class="text-sm font-medium text-gray-500">Personal Information</h5>
                                                    <div class="mt-2 border-t border-gray-200 pt-2">
                                                        <div class="grid grid-cols-2 gap-4">
                                                            <div>
                                                                <p class="text-xs text-gray-500">LRN:</p>
                                                                <p class="text-sm">${student.lrn || 'N/A'}</p>
                                                            </div>
                                                            <div>
                                                                <p class="text-xs text-gray-500">ULI:</p>
                                                                <p class="text-sm">${student.uli || 'N/A'}</p>
                                                            </div>
                                                            <div>
                                                                <p class="text-xs text-gray-500">Date of Birth:</p>
                                                                <p class="text-sm">${student.dob || 'N/A'}</p>
                                                            </div>
                                                            <div>
                                                                <p class="text-xs text-gray-500">Email:</p>
                                                                <p class="text-sm">${student.email || 'N/A'}</p>
                                                            </div>
                                                            <div>
                                                                <p class="text-xs text-gray-500">Contact:</p>
                                                                <p class="text-sm">${student.contact || 'N/A'}</p>
                                                            </div>
                                                            <div>
                                                                <p class="text-xs text-gray-500">Address:</p>
                                                                <p class="text-sm">${student.address || 'N/A'}</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div>
                                                    <h5 class="text-sm font-medium text-gray-500">Academic Information</h5>
                                                    <div class="mt-2 border-t border-gray-200 pt-2">
                                                        <div class="grid grid-cols-2 gap-4">
                                                            <div>
                                                                <p class="text-xs text-gray-500">Grade Level:</p>
                                                                <p class="text-sm">Grade ${student.grade_level || 'N/A'}</p>
                                                            </div>
                                                            <div>
                                                                <p class="text-xs text-gray-500">Registered On:</p>
                                                <p class="text-sm">${formatDate(student.created_at) || 'N/A'}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                  
                                    <div class="mt-2 border-t border-gray-200 pt-2">
                                        <div class="flex space-x-2">
                                           
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `);
                    
                    $('#student-modal').removeClass('hidden');
                } else {
                    console.error('Student not found with ID:', studentId);
                    $('#modal-content').html(`
                        <div class="text-center p-6">
                            <div class="text-red-500 text-xl mb-4">
                                <i class="fas fa-exclamation-circle fa-3x"></i>
                            </div>
                            <h4 class="text-xl font-bold mb-2">Student Not Found</h4>
                            <p class="text-gray-600">The student with ID ${studentId} could not be found.</p>
                        </div>
                    `);
                    $('#student-modal').removeClass('hidden');
                }
            }
            
            // Helper function to highlight search terms
            function highlightText(text, searchTerm) {
                if (!searchTerm || searchTerm === '') return text;
                
                const regex = new RegExp(`(${searchTerm})`, 'gi');
                return String(text).replace(regex, '<span class="search-highlight">$1</span>');
            }
            
            // Helper function to capitalize first letter
            function capitalizeFirstLetter(string) {
                return string.charAt(0).toUpperCase() + string.slice(1);
            }
            
            // Format date helper
            function formatDate(dateString) {
                if (!dateString) return 'N/A';
                
                try {
                    const date = new Date(dateString);
                    return date.toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric'
                    });
                } catch (e) {
                    return dateString;
                }
            }
            
            // Simulate online status updates occasionally
            setInterval(() => {
                // Randomly toggle a student's status for demonstration
                if (studentData.length > 0) {
                    const randomIndex = Math.floor(Math.random() * studentData.length);
                    const newStatus = studentData[randomIndex].online_status === 'active' ? 'offline' : 'active';
                    
                    // Update status
                    studentData[randomIndex].online_status = newStatus;
                    
                    // Re-render if the student is on the current page
                    const startIndex = (currentPage - 1) * itemsPerPage;
                    const endIndex = startIndex + itemsPerPage;
                    const isOnCurrentPage = randomIndex >= startIndex && randomIndex < endIndex;
                    
                    if (isOnCurrentPage) {
                        renderStudents();
                    }
                }
            }, 30000); // Every 30 seconds

            // Poll the server every 30 seconds to update real online status
            setInterval(function(){
                $.ajax({
                    url: 'check_online_status.php',
                    dataType: 'json',
                    success: function(statusMap) {
                        console.log("Status Map from server:", statusMap); // Debug output
                        studentData.forEach(student => {
                            if (statusMap[student.id]) {
                                student.online_status = statusMap[student.id];
                            }
                        });
                        renderStudents();
                    },
                    error: function(xhr, status, error) {
                        console.error("Failed to get online status:", error);
                    }
                });
            }, 30000);
        </script>
    </div>
</div>
</div>
</body>
</html>