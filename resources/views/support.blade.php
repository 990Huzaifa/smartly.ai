<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 1000px;
            width: 100%;
            overflow: hidden;
        }

        .content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }

        .form-section {
            padding: 50px;
            background: white;
        }

        .info-section {
            padding: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        h1 {
            font-size: 28px;
            margin-bottom: 10px;
            color: #333;
            font-weight: 700;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 14px;
            color: #333;
        }

        input,
        textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-family: inherit;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 120px;
        }

        button {
            width: 100%;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        button:active {
            transform: translateY(0);
        }

        .info-section h2 {
            font-size: 24px;
            margin-bottom: 30px;
            font-weight: 700;
        }

        .info-item {
            margin-bottom: 25px;
        }

        .info-item h3 {
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
            opacity: 0.9;
        }

        .info-item p {
            font-size: 15px;
            line-height: 1.8;
            opacity: 0.95;
        }

        .info-item a {
            color: white;
            text-decoration: none;
            transition: opacity 0.3s ease;
        }

        .info-item a:hover {
            opacity: 0.8;
        }

        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .social-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .social-icon:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-3px);
        }

        .success-message {
            display: none;
            position: fixed;
            top: 20px;
            right: 20px;
            background: #10b981;
            color: white;
            padding: 16px 24px;
            border-radius: 6px;
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
            animation: slideIn 0.3s ease;
            z-index: 1000;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .content {
                grid-template-columns: 1fr;
            }

            .form-section,
            .info-section {
                padding: 30px;
            }

            h1 {
                font-size: 24px;
            }

            .info-section h2 {
                font-size: 20px;
            }

            .social-links {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="content">
            <!-- Form Section -->
            <div class="form-section">
                <h1>Get in Touch</h1>
                <p class="subtitle">We'd love to hear from you. Send us a message!</p>
                
                <form id="contactForm">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone">
                    </div>

                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" required>
                    </div>

                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" required></textarea>
                    </div>

                    <button type="submit">Send Message</button>
                </form>
            </div>

            <!-- Info Section -->
            <div class="info-section">
                <h2>Contact Information</h2>

                <div class="info-item">
                    <h3>Email</h3>
                    <p><a href="mailto:minhaj.ali.khan909@gmail.com">minhaj.ali.khan909@gmail.com</a></p>
                </div>

                <div class="info-item">
                    <h3>Phone</h3>
                    <p><a href="tel:+923322660835">+92 332 2660835</a></p>
                </div>
            </div>
        </div>
    </div>

    <div class="success-message" id="successMessage">
        ✓ Message sent successfully! We'll get back to you soon.
    </div>

    <script>
        const form = document.getElementById('contactForm');
        const successMessage = document.getElementById('successMessage');

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // Get form values
            const name = document.getElementById('name').value;
            const email = document.getElementById('email').value;
            const phone = document.getElementById('phone').value;
            const subject = document.getElementById('subject').value;
            const message = document.getElementById('message').value;

            // Log form data (in a real app, you'd send this to a server)
            console.log('[v0] Form submitted:', {
                name,
                email,
                phone,
                subject,
                message
            });

            // Show success message
            successMessage.style.display = 'block';

            // Reset form
            form.reset();

            // Hide success message after 5 seconds
            setTimeout(() => {
                successMessage.style.display = 'none';
            }, 5000);
        });
    </script>
</body>
</html>

    <script>
        $(document).ready(function () {
            $("#contactForm").on("submit", function (e) {
                e.preventDefault();

                let formData = new FormData(this);

                $.ajax({
                    url: "/",  // Update with the correct route
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        $('#responseMessage').html('<div class="alert alert-success">Your message has been sent successfully!</div>').show();
                        $('#contactForm')[0].reset();
                    },
                    error: function (xhr, status, error) {
                        $('#responseMessage').html('<div class="alert alert-danger">There was an error submitting your message. Please try again.</div>').show();
                    }
                });
            });
        });
    </script>