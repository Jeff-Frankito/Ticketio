<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Verify your Ticketio account</title>
</head>
<body>
  <h2>Welcome to Ticketio!</h2>

  <p>Hello {{ $name }},</p>

  <p>Thank you for registering. Please verify your email address to activate your login.</p>

  <p>
    <a href="{{ $url }}">Verify email address</a>
  </p>

  <p>This link expires in 24 hours.</p>

  <p>If you didn't create an account, you can ignore this email.</p>
</body>
</html>
