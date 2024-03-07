<?php
/**
 * Email Template
 *
 * @package BackupBuddy
 */

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
	<meta charset="UTF-8">
	<meta content="width=device-width, initial-scale=1" name="viewport">
	<meta name="x-apple-disable-message-reformatting">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta content="telephone=no" name="format-detection">
	<title>{body_title}</title><!--[if (mso 16)]>
	<style type="text/css">
		a {
			text-decoration: none;
		}
	</style>
	<![endif]--><!--[if gte mso 9]>
	<style>sup {
		font-size: 100% !important;
	}</style><![endif]--><!--[if gte mso 9]>
	<xml>
		<o:OfficeDocumentSettings>
			<o:AllowPNG></o:AllowPNG>
			<o:PixelsPerInch>96</o:PixelsPerInch>
		</o:OfficeDocumentSettings>
	</xml>
	<![endif]-->
	<style type="text/css">
		.rollover:hover .rollover-first {
			max-height: 0px !important;
			display: none !important;
		}

		.rollover:hover .rollover-second {
			max-height: none !important;
			display: inline-block !important;
		}

		.rollover div {
			font-size: 0px;
		}

		u ~ div img + div > div {
			display: none;
		}

		#outlook a {
			padding: 0;
		}

		span.MsoHyperlink,
		span.MsoHyperlinkFollowed {
			color: inherit;
			mso-style-priority: 99;
		}

		a.es-button {
			mso-style-priority: 100 !important;
			text-decoration: none !important;
		}

		a[x-apple-data-detectors] {
			color: inherit !important;
			text-decoration: none !important;
			font-size: inherit !important;
			font-family: inherit !important;
			font-weight: inherit !important;
			line-height: inherit !important;
		}

		.es-desk-hidden {
			display: none;
			float: left;
			overflow: hidden;
			width: 0;
			max-height: 0;
			line-height: 0;
			mso-hide: all;
		}

		.es-header-body a:hover {
			color: #666666 !important;
		}

		.es-content-body a:hover {
			color: #5c68e2 !important;
		}

		.es-footer-body a:hover {
			color: #333333 !important;
		}

		.es-infoblock a:hover {
			color: #cccccc !important;
		}

		.es-button-border:hover > a.es-button {
			color: #ffffff !important;
		}

		@media only screen and (max-width: 600px) {
			.es-m-p0r {
				padding-right: 0px !important
			}

			.es-m-p0 {
				padding: 0px !important
			}

			*[class="gmail-fix"] {
				display: none !important
			}

			p, a {
				line-height: 150% !important
			}

			h1, h1 a {
				line-height: 120% !important
			}

			h2, h2 a {
				line-height: 120% !important
			}

			h3, h3 a {
				line-height: 120% !important
			}

			h4, h4 a {
				line-height: 120% !important
			}

			h5, h5 a {
				line-height: 120% !important
			}

			h6, h6 a {
				line-height: 120% !important
			}

			h1 {
				font-size: 36px !important;
				text-align: left
			}

			h2 {
				font-size: 26px !important;
				text-align: left
			}

			h3 {
				font-size: 20px !important;
				text-align: left
			}

			h4 {
				font-size: 24px !important;
				text-align: left
			}

			h5 {
				font-size: 20px !important;
				text-align: left
			}

			h6 {
				font-size: 16px !important;
				text-align: left
			}

			.es-header-body h1 a, .es-content-body h1 a, .es-footer-body h1 a {
				font-size: 36px !important
			}

			.es-header-body h2 a, .es-content-body h2 a, .es-footer-body h2 a {
				font-size: 26px !important
			}

			.es-header-body h3 a, .es-content-body h3 a, .es-footer-body h3 a {
				font-size: 20px !important
			}

			.es-header-body h4 a, .es-content-body h4 a, .es-footer-body h4 a {
				font-size: 24px !important
			}

			.es-header-body h5 a, .es-content-body h5 a, .es-footer-body h5 a {
				font-size: 20px !important
			}

			.es-header-body h6 a, .es-content-body h6 a, .es-footer-body h6 a {
				font-size: 16px !important
			}

			.es-menu td a {
				font-size: 12px !important
			}

			.es-header-body p, .es-header-body a {
				font-size: 14px !important
			}

			.es-content-body p, .es-content-body a {
				font-size: 14px !important
			}

			.es-footer-body p, .es-footer-body a {
				font-size: 14px !important
			}

			.es-infoblock p, .es-infoblock a {
				font-size: 12px !important
			}

			.es-m-txt-c, .es-m-txt-c h1, .es-m-txt-c h2, .es-m-txt-c h3, .es-m-txt-c h4, .es-m-txt-c h5, .es-m-txt-c h6 {
				text-align: center !important
			}

			.es-m-txt-r, .es-m-txt-r h1, .es-m-txt-r h2, .es-m-txt-r h3, .es-m-txt-r h4, .es-m-txt-r h5, .es-m-txt-r h6 {
				text-align: right !important
			}

			.es-m-txt-j, .es-m-txt-j h1, .es-m-txt-j h2, .es-m-txt-j h3, .es-m-txt-j h4, .es-m-txt-j h5, .es-m-txt-j h6 {
				text-align: justify !important
			}

			.es-m-txt-l, .es-m-txt-l h1, .es-m-txt-l h2, .es-m-txt-l h3, .es-m-txt-l h4, .es-m-txt-l h5, .es-m-txt-l h6 {
				text-align: left !important
			}

			.es-m-txt-r img, .es-m-txt-c img, .es-m-txt-l img {
				display: inline !important
			}

			.es-m-txt-r .rollover:hover .rollover-second, .es-m-txt-c .rollover:hover .rollover-second, .es-m-txt-l .rollover:hover .rollover-second {
				display: inline !important
			}

			.es-m-txt-r .rollover div, .es-m-txt-c .rollover div, .es-m-txt-l .rollover div {
				line-height: 0 !important;
				font-size: 0 !important
			}

			.es-spacer {
				display: inline-table
			}

			a.es-button, button.es-button {
				font-size: 20px !important
			}

			a.es-button, button.es-button {
				display: inline-block !important
			}

			.es-button-border {
				display: inline-block !important
			}

			.es-m-fw, .es-m-fw.es-fw, .es-m-fw .es-button {
				display: block !important
			}

			.es-m-il, .es-m-il .es-button, .es-social, .es-social td, .es-menu {
				display: inline-block !important
			}

			.es-adaptive table, .es-left, .es-right {
				width: 100% !important
			}

			.es-content table, .es-header table, .es-footer table, .es-content, .es-footer, .es-header {
				width: 100% !important;
				max-width: 600px !important
			}

			.adapt-img {
				width: 100% !important;
				height: auto !important
			}

			.es-mobile-hidden, .es-hidden {
				display: none !important
			}

			.es-desk-hidden {
				width: auto !important;
				overflow: visible !important;
				float: none !important;
				max-height: inherit !important;
				line-height: inherit !important
			}

			tr.es-desk-hidden {
				display: table-row !important
			}

			table.es-desk-hidden {
				display: table !important
			}

			td.es-desk-menu-hidden {
				display: table-cell !important
			}

			.es-menu td {
				width: 1% !important
			}

			table.es-table-not-adapt, .esd-block-html table {
				width: auto !important
			}

			.es-social td {
				padding-bottom: 10px
			}

			.h-auto {
				height: auto !important
			}
		}
	</style>
</head>
<body style="width:100%;height:100%;padding:0;Margin:0">
<div class="es-wrapper-color" style="background-color:#F6F7F7"><!--[if gte mso 9]>
	<v:background xmlns:v="urn:schemas-microsoft-com:vml" fill="t">
		<v:fill type="tile" color="#F6F7F7"></v:fill>
	</v:background>
	<![endif]-->
	<table class="es-wrapper" width="100%" cellspacing="0" cellpadding="0"
		   style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;padding:0;Margin:0;width:100%;height:100%;background-repeat:repeat;background-position:center top;background-color:#F6F7F7">
		<tr>
			<td valign="top" style="padding:0;Margin:0">
				<table cellpadding="0" cellspacing="0" class="es-content" align="center"
					   style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;width:100%;table-layout:fixed !important">
					<tr>
						<td align="center" bgcolor="#E9E7EE" style="padding:0;Margin:0;background-color:#e9e7ee">
							<table class="es-content-body" align="center" cellpadding="0" cellspacing="0"
								   style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-color:transparent;width:600px" bgcolor="#FFFFFF">
								<tr>
									<td align="left" style="padding:0;Margin:0;padding-right:20px;padding-left:20px">
										<table cellpadding="0" cellspacing="0" width="100%" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
											<tr>
												<td align="center" valign="top" style="padding:0;Margin:0;width:560px">
													<table cellpadding="0" cellspacing="0" width="100%" role="presentation"
														   style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
														<tr>
															<td align="center" class="es-infoblock" style="padding:10px;Margin:0">
																<p style="Margin:0;mso-line-height-rule:exactly;font-family:helvetica, 'helvetica neue', arial, verdana, sans-serif;line-height:18px;letter-spacing:0;color:#232323;font-size:12px">
																	{banner}<a target="_blank"  style="mso-line-height-rule:exactly;text-decoration:underline;color:#3c1596;font-size:12px" href="{site_url}">{site_url}</a></p></td>
														</tr>
													</table>
												</td>
											</tr>
										</table>
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>
				<table cellpadding="0" cellspacing="0" class="es-header" align="center"
					   style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;width:100%;table-layout:fixed !important;background-color:transparent;background-repeat:repeat;background-position:center top">
					<tr>
						<td align="center" style="padding:0;Margin:0">
							<table bgcolor="#ffffff" class="es-header-body" align="center" cellpadding="0" cellspacing="0"
								   style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-color:transparent;width:600px">
								<tr>
									<td align="left" style="Margin:0;padding-right:20px;padding-left:20px;padding-top:10px;padding-bottom:10px">
										<table cellpadding="0" cellspacing="0" width="100%" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
											<tr>
												<td class="es-m-p0r" valign="top" align="center" style="padding:0;Margin:0;width:560px">
													<table cellpadding="0" cellspacing="0" width="100%" role="presentation"
														   style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
														<tr>
															<td align="center" style="padding:0;Margin:0;padding-top:30px;padding-bottom:30px;font-size:0px"><img
																		src="{logo_url}"
																		alt="Logo" style="display:block;font-size:12px;border:0;outline:none;text-decoration:none" width="351" title="Logo"></td>
														</tr>
													</table>
												</td>
											</tr>
										</table>
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>
				<table cellpadding="0" cellspacing="0" class="es-content" align="center"
					   style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;width:100%;table-layout:fixed !important">
					<tr>
						<td align="center" style="padding:0;Margin:0">
							<table bgcolor="#ffffff" class="es-content-body" align="center" cellpadding="0" cellspacing="0"
								   style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-color:#FFFFFF;width:600px">
								<tr>
									<td align="left" style="padding:0;Margin:0;padding-right:40px;padding-bottom:40px;padding-left:40px">
										<table cellpadding="0" cellspacing="0" width="100%" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
											<tr>
												<td align="center" valign="top" style="padding:0;Margin:0;width:520px">
													<table cellpadding="0" cellspacing="0" width="100%" role="presentation"
														   style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
														<tr>
															<td align="center" class="es-m-txt-c" style="padding:0;Margin:0;padding-top:20px;padding-bottom:5px"><h1
																		style="Margin:0;font-family:helvetica, 'helvetica neue', arial, verdana, sans-serif;mso-line-height-rule:exactly;letter-spacing:0;font-size:24px;font-style:normal;font-weight:bold;line-height:29px;color:#333333;text-align:left">
																	{heading}</h1></td>
														</tr>
														<tr>
															<td align="left" style="padding:0;Margin:0"><p
																		style="Margin:0;mso-line-height-rule:exactly;font-family:helvetica, 'helvetica neue', arial, verdana, sans-serif;line-height:24px;letter-spacing:0;color:#545454;font-size:16px">
																	{site_url}</p></td>
														</tr>
													</table>
												</td>
											</tr>
										</table>
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>
				<table cellpadding="0" cellspacing="0" class="es-content" align="center"
					   style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;width:100%;table-layout:fixed !important">
					<tr>
						<td align="center" style="padding:0;Margin:0">
							<table bgcolor="#ffffff" class="es-content-body" align="center" cellpadding="0" cellspacing="0"
								   style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-color:#ffffff;width:600px">
								<tr>
									<td align="left" style="padding:0;Margin:0;padding-right:40px;padding-bottom:40px;padding-left:40px">
										<table cellpadding="0" cellspacing="0" width="100%" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
											<tr>
												<td align="center" valign="top" style="padding:0;Margin:0;width:520px">
													<table cellpadding="0" cellspacing="0" width="100%" role="presentation"
														   style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
														<tr>
															<td align="left" style="padding:0;Margin:0;padding-bottom:20px"><p
																		style="Margin:0;mso-line-height-rule:exactly;font-family:helvetica, 'helvetica neue', arial, verdana, sans-serif;line-height:24px;letter-spacing:0;color:#232323;font-size:16px">
																	{body}
														</tr>
													</table>
												</td>
											</tr>
										</table>
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>
				<!-- Unsubscribe Text -->
				<table cellpadding="0" cellspacing="0" class="es-footer" align="center"
					   style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;width:100%;table-layout:fixed !important;background-color:transparent;background-repeat:repeat;background-position:center top">
					<tr>
						<td align="center" style="padding:0;Margin:0">
							<table class="es-footer-body" align="center" cellpadding="0" cellspacing="0"
								   style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-color:transparent;width:600px">
								<tr>
									<td align="left" style="padding:20px;Margin:0">
										<table cellpadding="0" cellspacing="0" width="100%" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
											<tr>
												<td align="center" valign="top" style="padding:0;Margin:0;width:560px">
													<table cellpadding="0" cellspacing="0" width="100%" role="presentation"
														   style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
														<tr>
															<td align="center" class="es-infoblock" style="padding:0;Margin:0;padding-top:40px"><p
																		style="Margin:0;mso-line-height-rule:exactly;font-family:helvetica, 'helvetica neue', arial, verdana, sans-serif;line-height:18px;letter-spacing:0;color:#545454;font-size:12px">
																	This email was generated by the Solid Backups plugin.</p>
																<p style="Margin:0;mso-line-height-rule:exactly;font-family:helvetica, 'helvetica neue', arial, verdana, sans-serif;line-height:18px;letter-spacing:0;color:#545454;font-size:12px">
																	To unsubscribe from these updates, visit&nbsp;Solid Backups &gt; Stash Live then click on settings menu.</p></td>
														</tr>
													</table>
												</td>
											</tr>
										</table>
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</div>
</body>
</html>