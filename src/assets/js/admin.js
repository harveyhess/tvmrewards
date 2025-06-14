document.addEventListener('DOMContentLoaded', function() {
    console.log('Admin.js loaded');
    
    // Points edit functionality
    const pointsModal = document.getElementById('pointsModal');
    const qrModal = document.getElementById('qrModal');
    const pointsForm = document.getElementById('pointsForm');
    const uploadForm = document.querySelector('form[action="process_upload.php"]');
    const rewardModal = document.getElementById('rewardModal');
    const rewardForm = document.getElementById('rewardForm');

    console.log('Modal elements:', {
        pointsModal,
        qrModal,
        rewardModal
    });

    // Reward form submission
    if (rewardForm) {
        console.log('Reward form found');
        rewardForm.addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('Reward form submitted');
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            console.log('Form data:', data);
            
            fetch('save_reward.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                console.log('Save reward response:', data);
                if (data.success) {
                    location.reload();
                } else {
                    showFlashMessage(data.error || 'Error saving reward', 'error');
                }
            })
            .catch(error => {
                console.error('Error saving reward:', error);
                showFlashMessage('Error saving reward', 'error');
            });
        });
    } else {
        console.log('Reward form not found');
    }

    // Edit reward button click
    const editButtons = document.querySelectorAll('.edit-reward');
    console.log('Edit reward buttons:', editButtons.length);
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            console.log('Edit reward button clicked');
            const rewardId = this.dataset.id;
            console.log('Reward ID:', rewardId);
            fetch(`get_reward.php?id=${rewardId}`)
                .then(response => response.json())
                .then(reward => {
                    console.log('Reward data:', reward);
                    document.getElementById('rewardId').value = reward.id;
                    document.getElementById('rewardName').value = reward.name;
                    document.getElementById('description').value = reward.description;
                    document.getElementById('pointsCost').value = reward.points_cost;
                    document.getElementById('isActive').value = reward.is_active;
                    document.getElementById('modalTitle').textContent = 'Edit Reward';
                    rewardModal.style.display = 'block';
                })
                .catch(error => {
                    console.error('Error loading reward:', error);
                    showFlashMessage('Error loading reward details', 'error');
                });
        });
    });

    // Delete reward button click
    const deleteButtons = document.querySelectorAll('.delete-reward');
    console.log('Delete reward buttons:', deleteButtons.length);
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            console.log('Delete reward button clicked');
            if (confirm('Are you sure you want to delete this reward?')) {
                const rewardId = this.dataset.id;
                console.log('Deleting reward ID:', rewardId);
                fetch('delete_reward.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: rewardId })
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Delete reward response:', data);
                    if (data.success) {
                        location.reload();
                    } else {
                        showFlashMessage(data.error || 'Error deleting reward', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error deleting reward:', error);
                    showFlashMessage('Error deleting reward', 'error');
                });
            }
        });
    });

    // Show add reward modal
    window.showAddRewardModal = function() {
        console.log('Showing add reward modal');
        document.getElementById('modalTitle').textContent = 'Add New Reward';
        rewardForm.reset();
        document.getElementById('rewardId').value = '';
        document.getElementById('isActive').value = '1';
        rewardModal.style.display = 'block';
    };

    // Close reward modal
    document.querySelectorAll('.modal .close').forEach(button => {
        button.addEventListener('click', function() {
            console.log('Closing modal');
            this.closest('.modal').style.display = 'none';
        });
    });

    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            console.log('Closing modal (outside click)');
            event.target.style.display = 'none';
        }
    });

    // Handle file upload form submission
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const fileInput = formData.get('csv_file');
            
            if (!fileInput || !fileInput.name) {
                showFlashMessage('Please select a CSV file', 'error');
                return;
            }
            
            if (!fileInput.name.toLowerCase().endsWith('.csv')) {
                showFlashMessage('Only CSV files are allowed', 'error');
                return;
            }
            
            // Submit the form
            this.submit();
        });
    }

    // Edit points button click
    document.querySelectorAll('.edit-points').forEach(button => {
        button.addEventListener('click', function() {
            const patientId = this.dataset.patientId;
            const points = this.parentElement.querySelector('.points').textContent;
            
            document.getElementById('patientId').value = patientId;
            document.getElementById('points').value = points;
            pointsModal.style.display = 'block';
        });
    });

    // QR code button click
    document.querySelectorAll('.qr-code').forEach(button => {
        button.addEventListener('click', function() {
            const patientId = this.dataset.patientId;
            const qrModal = document.getElementById('qrModal');
            const qrCode = document.getElementById('qrCode');
            
            // Show loading state
            qrCode.innerHTML = '<p>Generating QR code...</p>';
            qrModal.style.display = 'block';
            
            fetch(`../../admin/generate_qr.php?PatientID=${patientId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.qr_code_url) {
                        qrCode.innerHTML = `
                            <div class="qr-container">
                                <img src="${data.qr_code_url}" 
                                     alt="Patient QR Code" 
                                     style="max-width: 300px; margin: 20px auto;"
                                     onerror="this.onerror=null; this.src='https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(data.url)}'">
                                <div class="qr-info">
                                    <p><strong>Patient ID:</strong> ${data.PatientID}</p>
                                    <p><strong>Name:</strong> ${data.name}</p>
                                    <p><strong>Phone:</strong> ${data.phone}</p>
                                    <p><strong>Login URL:</strong></p>
                                    <p><a href="${data.url}" target="_blank">${data.url}</a></p>
                                    <p class="qr-note">Scan this QR code to automatically log in to the patient dashboard.</p>
                                </div>
                            </div>
                        `;
                    } else {
                        qrCode.innerHTML = `<p class="error">Error: ${data.error || 'Failed to generate QR code'}</p>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    qrCode.innerHTML = '<p class="error">Error generating QR code</p>';
                });
        });
    });

    // Handle points form submission
    if (pointsForm) {
        pointsForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('../../admin/handle_update_points.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update points in the table
                    const pointsElement = document.querySelector(`[data-patient-id="${formData.get('PatientID')}"]`)
                        .parentElement.querySelector('.points');
                    pointsElement.textContent = formData.get('points');
                    
                    // Close modal
                    pointsModal.style.display = 'none';
                    
                    // Show success message
                    showFlashMessage('Points updated successfully', 'success');
                } else {
                    showFlashMessage(data.error || 'Error updating points', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showFlashMessage('Error updating points', 'error');
            });
        });
    }
});

// Flash message function
function showFlashMessage(message, type) {
    const flashMessage = document.createElement('div');
    flashMessage.className = `flash-message ${type}`;
    flashMessage.textContent = message;
    
    const main = document.querySelector('main');
    main.insertBefore(flashMessage, main.firstChild);
    
    setTimeout(() => {
        flashMessage.remove();
    }, 5000);
} 