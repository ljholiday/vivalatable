<?php
/**
 * VivalaTable Invitation Email Template
 * Event invitation email with inline CSS for better email client compatibility
 * Ported from PartyMinder WordPress plugin
 */

// Set subject for email
$subject = sprintf('You\'re invited: %s', $event_title);

// Create inline CSS for better email client compatibility
$styles = array(
	'container' => 'max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; line-height: 1.6; color: #333;',
	'header' => 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center;',
	'body' => 'background: #ffffff; padding: 30px 20px;',
	'event_card' => 'background: #f8f9ff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 20px 0;',
	'btn_primary' => 'display: inline-block; background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 8px 8px 8px 0;',
	'btn_secondary' => 'display: inline-block; background: #e2e8f0; color: #4a5568; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 8px 8px 8px 0;',
	'btn_danger' => 'display: inline-block; background: #f56565; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 8px 8px 8px 0;',
	'footer' => 'background: #f7fafc; color: #718096; padding: 20px; text-align: center; font-size: 12px;',
);

// Format event date/time
$event_day = date('l', strtotime($event_date ?? ''));
$event_date_formatted = date('F j, Y', strtotime($event_date ?? ''));
$event_time_formatted = date('g:i A', strtotime($event_date ?? ''));

// Generate RSVP URLs
$rsvp_yes_url = $invitation_url . '&rsvp=yes';
$rsvp_maybe_url = $invitation_url . '&rsvp=maybe';
$rsvp_no_url = $invitation_url . '&rsvp=no';

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo htmlspecialchars($subject); ?></title>
</head>
<body style="margin: 0; padding: 20px; background-color: #f7fafc;">
	<div style="<?php echo $styles['container']; ?>">
		<!-- Header -->
		<div style="<?php echo $styles['header']; ?>">
			<h1 style="margin: 0; font-size: 24px;">You're Invited!</h1>
			<p style="margin: 10px 0 0 0; opacity: 0.9;"><?php echo htmlspecialchars($from_name); ?> has invited you to an event</p>
		</div>

		<!-- Body -->
		<div style="<?php echo $styles['body']; ?>">
			<?php if (!empty($custom_message)) : ?>
				<div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 15px; margin-bottom: 20px;">
					<strong>Personal message from <?php echo htmlspecialchars($from_name); ?>:</strong><br>
					<em><?php echo htmlspecialchars($custom_message); ?></em>
				</div>
			<?php endif; ?>

			<!-- Event Details -->
			<div style="<?php echo $styles['event_card']; ?>">
				<h2 style="margin: 0 0 15px 0; color: #2d3748; font-size: 20px;"><?php echo htmlspecialchars($event_title); ?></h2>

				<div style="margin-bottom: 10px;">
					<strong>📅 When:</strong> <?php echo $event_day; ?>, <?php echo $event_date_formatted; ?> at <?php echo $event_time_formatted; ?>
				</div>

				<?php if (!empty($venue_info)) : ?>
				<div style="margin-bottom: 10px;">
					<strong>📍 Where:</strong> <?php echo htmlspecialchars($venue_info); ?>
				</div>
				<?php endif; ?>

				<?php if (!empty($event_description)) : ?>
				<div style="margin-top: 15px;">
					<strong>About:</strong><br>
					<?php echo htmlspecialchars(VT_Text::truncateWords($event_description, 25)); ?>
				</div>
				<?php endif; ?>
			</div>

			<!-- Quick RSVP Buttons -->
			<div style="text-align: center; margin: 30px 0;">
				<h3 style="color: #2d3748; margin-bottom: 15px;">Quick RSVP:</h3>
				<div>
					<a href="<?php echo htmlspecialchars($rsvp_yes_url); ?>" style="<?php echo $styles['btn_primary']; ?>">
						✅ Yes, I'll be there!
					</a>
					<a href="<?php echo htmlspecialchars($rsvp_maybe_url); ?>" style="<?php echo $styles['btn_secondary']; ?>">
						🤔 Maybe
					</a>
					<a href="<?php echo htmlspecialchars($rsvp_no_url); ?>" style="<?php echo $styles['btn_danger']; ?>">
						❌ Can't make it
					</a>
				</div>
				<p style="margin-top: 20px; font-size: 14px; color: #718096;">
					Or <a href="<?php echo htmlspecialchars($invitation_url); ?>" style="color: #667eea;">click here to RSVP with more details</a>
				</p>
			</div>

			<!-- About VivalaTable -->
			<div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
				<p style="color: #718096; font-size: 14px; margin: 0;">
					This invitation was sent through <strong><?php echo htmlspecialchars($site_name); ?></strong> - making event planning simple and social.
				</p>
				<p style="margin: 10px 0 0 0;">
					<a href="<?php echo htmlspecialchars($site_url); ?>" style="color: #667eea; font-size: 12px;">Learn more about <?php echo htmlspecialchars($site_name); ?></a>
				</p>
			</div>
		</div>

		<!-- Footer -->
		<div style="<?php echo $styles['footer']; ?>">
			<p style="margin: 0;">Having trouble with the buttons? Copy and paste this link: <?php echo htmlspecialchars($invitation_url); ?></p>
			<p style="margin: 10px 0 0 0;">
				© <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_name); ?>. All rights reserved.
			</p>
		</div>
	</div>
</body>
</html>