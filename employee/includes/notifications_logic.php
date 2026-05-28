<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let lastNotificationTime = 0;

    function speakText(text) {
        if ('speechSynthesis' in window) {
            // Cancel any current speech
            window.speechSynthesis.cancel();
            
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.rate = 0.9;
            utterance.pitch = 1;
            utterance.volume = 1;
            
            // Try to find a female/boutique-style voice if possible
            const voices = window.speechSynthesis.getVoices();
            if (voices.length > 0) {
                // Prefer local or high quality voices
                utterance.voice = voices.find(v => v.name.includes('Female') || v.name.includes('Google')) || voices[0];
            }
            
            window.speechSynthesis.speak(utterance);
        }
    }

    function checkNewOrders() {
        fetch('check-new-assignments.php')
            .then(response => response.json())
            .then(data => {
                // Task Notifications
                if (data.new_orders > 0) {
                    speakText(data.order_message);
                    Swal.fire({
                        title: 'New Order Assigned!',
                        text: data.order_message,
                        icon: 'info',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 5000,
                        timerProgressBar: true,
                        background: '#fdf2f8',
                        color: '#db2777',
                        iconColor: '#db2777'
                    });
                }

                // Shift Notifications
                if (data.shift_updates > 0) {
                    speakText(data.shift_message);
                    Swal.fire({
                        title: 'Shift Update!',
                        text: data.shift_message,
                        icon: data.shift_status === 'Approved' ? 'success' : 'warning',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 5000,
                        timerProgressBar: true,
                        background: data.shift_status === 'Approved' ? '#f0fdf4' : '#fffbeb',
                        color: data.shift_status === 'Approved' ? '#166534' : '#92400e',
                        iconColor: data.shift_status === 'Approved' ? '#166534' : '#92400e'
                    });
                }
            })
            .catch(err => console.error('Notification check failed:', err));
    }

    // Initial check after 2 seconds
    setTimeout(checkNewOrders, 2000);

    // Check every 30 seconds
    setInterval(checkNewOrders, 30000);

    // Handle browser speech synthesis initialization on first click
    document.addEventListener('click', function() {
        if (window.speechSynthesis.paused) {
            window.speechSynthesis.resume();
        }
    }, { once: true });
</script>
