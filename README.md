# Xtrusio Amazon SES Plugin

Amazon SES email transport plugin for Xtrusio (Mautic 7.x compatible) with full bounce, complaint, and delivery tracking via SNS webhooks.

## Requirements

- Xtrusio 7.x (or Mautic 7.x)
- PHP 8.2+
- AWS account with SES access
- SES verified domain or email address

## Installation

### Option 1: Copy to plugins directory

```bash
cp -r XtrusioAmazonSesBundle /path/to/xtrusio/plugins/
cd /path/to/xtrusio
composer require symfony/amazon-mailer --ignore-platform-req=ext-imap
php bin/console cache:clear
```

### Option 2: Composer (if published)

```bash
composer require xtrusio/plugin-amazon-ses
```

After installing, go to **Settings > Plugins** and click **"Install/Upgrade Plugins"**.

## Configuration

### DSN Format

Set the mailer DSN in your Xtrusio configuration:

```
ses+api://ACCESS_KEY:SECRET_KEY@REGION
```

**Examples:**

```
ses+api://AKIAIOSFODNN7EXAMPLE:wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY@us-east-1
ses+api://AKIAIOSFODNN7EXAMPLE:wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY@eu-west-1
```

**With Configuration Set (for event tracking):**

```
ses+api://ACCESS_KEY:SECRET_KEY@REGION?configuration_set=my-config-set
```

### Setting via UI

1. Go to **Settings > Email Settings**
2. Set **Mailer Transport** to the DSN above
3. Save

### Setting via config/local.php

```php
'mailer_dsn' => 'ses+api://ACCESS_KEY:SECRET_KEY@us-east-1',
```

## AWS Setup for Bounce/Complaint Tracking

### Step 1: Create SNS Topic

1. Go to **AWS SNS Console**
2. Create a new topic (e.g., `xtrusio-ses-notifications`)
3. Note the Topic ARN

### Step 2: Subscribe to SNS Topic

Create an HTTPS subscription pointing to your Xtrusio webhook:

```
https://your-xtrusio-domain.com/mailer/callback
```

The plugin will automatically confirm the SNS subscription.

### Step 3: Configure SES Notifications

1. Go to **AWS SES Console > Verified Identities**
2. Select your verified domain/email
3. Go to **Notifications** tab
4. Set **Bounce**, **Complaint**, and **Delivery** notifications to your SNS topic

### Step 4: (Recommended) Create Configuration Set

1. Go to **SES > Configuration Sets**
2. Create a new set (e.g., `xtrusio-tracking`)
3. Add an **SNS event destination** for Bounce, Complaint, Delivery, and Reject events
4. Add `?configuration_set=xtrusio-tracking` to your DSN

## How It Works

### Sending
- Emails are sent via the SES v2 `SendEmail` API using raw MIME messages
- Supports batch sending (up to 50 recipients per batch)
- Includes tracking hash in email tags for delivery correlation

### Bounce Handling
- Hard bounces (Permanent) mark contacts as **Do Not Contact (Bounced)**
- Soft bounces (Transient) are logged for monitoring
- Bounce details stored in email stats

### Complaint Handling
- Spam complaints mark contacts as **Do Not Contact (Unsubscribed)**
- Complaint type (abuse, auth-failure, etc.) logged

### Delivery Tracking
- Successful deliveries are logged for audit purposes

## Webhook Endpoint

```
POST /mailer/callback
```

Accepts:
- SNS SubscriptionConfirmation (auto-confirmed)
- SNS Notification wrapping SES events
- Direct SES event JSON

## Troubleshooting

### Test email works but campaign sending doesn't
- Check SES sending limits in AWS console
- Verify you're out of the SES sandbox (or recipients are verified)

### Bounces not being recorded
- Verify SNS subscription is confirmed (check AWS SNS console)
- Check that the webhook URL is publicly accessible
- Check Xtrusio logs at `var/logs/`

### "Access Denied" errors
- Verify IAM user has `ses:SendEmail` and `ses:SendRawEmail` permissions
- Check that the access key and secret key are correct

## IAM Policy

Minimum required IAM permissions:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "ses:SendEmail",
                "ses:SendRawEmail"
            ],
            "Resource": "*"
        }
    ]
}
```

## License

GPL-3.0-or-later
