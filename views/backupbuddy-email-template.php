<?php
/**
 * Email Template
 *
 * @package BackupBuddy
 */

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<title>{body_title}</title>

	<!--[if gte mso 6]>
	<style>
		table.mcnFollowContent {width:100% !important;}
		table.mcnShareContent {width:100% !important;}
	</style>
	<![endif]-->
	<style type="text/css">
		#outlook a{
			padding:0;
		}
		.ReadMsgBody{
			width:100%;
		}
		.ExternalClass{
			width:100%;
		}
		body{
			margin:0;
			padding:0;
		}
		a{
			word-wrap:break-word !important;
		}
		img{
			border:0;
			height:auto !important;
			line-height:100%;
			outline:none;
			text-decoration:none;
		}
		table,td{
			border-collapse:collapse;
			mso-table-lspace:0pt;
			mso-table-rspace:0pt;
		}
		#bodyTable,#bodyCell{
			height:100% !important;
			margin:0;
			padding:0;
			width:100% !important;
		}
		.mcnImage{
			vertical-align:bottom;
		}
		.mcnTextContent img{
			height:auto !important;
		}
		body,#bodyTable{
			background-color:#ecf1f4;
		}
		#bodyCell{
			border-top:0;
		}
		h1{
			color:#2a4143 !important;
			display:block;
			font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif;
			font-size:24px;
			font-style:normal;
			font-weight:bold;
			line-height:150%;
			letter-spacing:0px;
			margin:0;
			text-align:center;
		}
		h2{
			color:#4d7579 !important;
			display:block;
			font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif;
			font-size:30px;
			font-style:normal;
			font-weight:normal;
			line-height:150%;
			letter-spacing:0px;
			margin:0;
			text-align:center;
		}
		h3{
			color:#0084cb !important;
			display:block;
			font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif;
			font-size:18px;
			font-style:normal;
			font-weight:bold;
			line-height:150%;
			letter-spacing:normal;
			margin:0;
			text-align:left;
		}
		h4{
			color:#e63030 !important;
			display:block;
			font-family:Helvetica;
			font-size:14px;
			font-style:normal;
			font-weight:bold;
			line-height:200%;
			letter-spacing:1px;
			margin:0;
			text-align:center;
		}
		#templatePreheader{
			background-color:#ecf1f4;
			border-top:0px none ;
			border-bottom:0;
		}
		.preheaderContainer .mcnTextContent,.preheaderContainer .mcnTextContent p{
			color:#b7cbd3;
			font-family:Helvetica;
			font-size:12px;
			line-height:150%;
			text-align:left;
		}
		.preheaderContainer .mcnTextContent a{
			color:#b7cbd3;
			font-weight:normal;
			text-decoration:none;
		}
		#templateHeader{
			background-color:#ffffff;
			border-top:0;
			border-bottom:5px none ;
		}
		.headerContainer .mcnTextContent,.headerContainer .mcnTextContent p{
			color:#a9bcc4;
			font-family:Helvetica;
			font-size:12px;
			line-height:100%;
			text-align:left;
		}
		.headerContainer .mcnTextContent a{
			color:#a9bcc4;
			font-weight:bold;
			text-decoration:underline;
		}
		#templateBody{
			background-color:#aef4fa;
			border-top:0;
			border-bottom:0;
		}
		.bodyContainer .mcnTextContent,.bodyContainer .mcnTextContent p{
			color:#505050;
			font-family:Helvetica;
			font-size:14px;
			line-height:150%;
			text-align:left;
		}
		.bodyContainer .mcnTextContent a{
			color:#0084cb;
			font-weight:bold;
			text-decoration:none;
		}
		#templateFooter{
			background-color:#ffffff;
			border-top:0;
			border-bottom:0;
		}
		.footerContainer .mcnTextContent,.footerContainer .mcnTextContent p{
			color:#505050;
			font-family:Helvetica;
			font-size:14px;
			line-height:150%;
			text-align:left;
		}
		.footerContainer .mcnTextContent a{
			color:#0088cd;
			font-weight:normal;
			text-decoration:underline;
		}
		@media only screen and (max-width: 480px){
			body,table,td,p,a,li,blockquote{
				-webkit-text-size-adjust:none !important;
			}
			body{
				width:100% !important;
				min-width:100% !important;
			}
			table[class=mcnTextContentContainer]{
				width:100% !important;
			}
			table[class=mcnBoxedTextContentContainer]{
				width:100% !important;
			}
			table[class=mcpreview-image-uploader]{
				width:100% !important;
				display:none !important;
			}
			img[class=mcnImage]{
				width:100% !important;
			}
			table[class=mcnImageGroupContentContainer]{
				width:100% !important;
			}
			td[class=mcnImageGroupContent]{
				padding:9px !important;
			}
			td[class=mcnImageGroupBlockInner]{
				padding-bottom:0 !important;
				padding-top:0 !important;
			}
			tbody[class=mcnImageGroupBlockOuter]{
				padding-bottom:9px !important;
				padding-top:9px !important;
			}
			table[class=mcnCaptionTopContent],table[class=mcnCaptionBottomContent]{
				width:100% !important;
			}
			table[class=mcnCaptionLeftTextContentContainer],table[class=mcnCaptionRightTextContentContainer],table[class=mcnCaptionLeftImageContentContainer],table[class=mcnCaptionRightImageContentContainer],table[class=mcnImageCardLeftTextContentContainer],table[class=mcnImageCardRightTextContentContainer]{
				width:100% !important;
			}
			td[class=mcnImageCardLeftImageContent],td[class=mcnImageCardRightImageContent]{
				padding-right:18px !important;
				padding-left:18px !important;
				padding-bottom:0 !important;
			}
			td[class=mcnImageCardBottomImageContent]{
				padding-bottom:9px !important;
			}
			td[class=mcnImageCardTopImageContent]{
				padding-top:18px !important;
			}
			td[class=mcnImageCardLeftImageContent],td[class=mcnImageCardRightImageContent]{
				padding-right:18px !important;
				padding-left:18px !important;
				padding-bottom:0 !important;
			}
			td[class=mcnImageCardBottomImageContent]{
				padding-bottom:9px !important;
			}
			td[class=mcnImageCardTopImageContent]{
				padding-top:18px !important;
			}
			table[class=mcnCaptionLeftContentOuter] td[class=mcnTextContent],table[class=mcnCaptionRightContentOuter] td[class=mcnTextContent]{
				padding-top:9px !important;
			}
			td[class=mcnCaptionBlockInner] table[class=mcnCaptionTopContent]:last-child td[class=mcnTextContent]{
				padding-top:18px !important;
			}
			td[class=mcnBoxedTextContentColumn]{
				padding-left:18px !important;
				padding-right:18px !important;
			}
			table[class=templateContainer]{
				max-width:600px !important;
				width:100% !important;
			}
			h1{
				font-size:24px !important;
				line-height:150% !important;
			}
			h2{
				font-size:30px !important;
				line-height:150% !important;
			}
			h3{
				font-size:16px !important;
				line-height:150% !important;
			}
			h4{
				font-size:14px !important;
				line-height:150% !important;
			}
			table[class=mcnBoxedTextContentContainer] td[class=mcnTextContent]{
				font-size:14px !important;
				line-height:150% !important;
			}

			table[id=templatePreheader]{
				display:none !important;
			}

			table[id=templateHeader]{
				border-top:0 !important;
			}

			td[class=headerContainer] td[class=mcnTextContent]{
				font-size:11px !important;
				line-height:150% !important;
				padding-right:18px !important;
				padding-left:18px !important;
			}

			td[class=bodyContainer] td[class=mcnTextContent]{
				font-size:14px !important;
				line-height:150% !important;
				padding-right:18px !important;
				padding-left:18px !important;
			}

			td[class=footerContainer] td[class=mcnTextContent]{
				font-size:14px !important;
				line-height:150% !important;
				padding-right:18px !important;
				padding-left:18px !important;
			}

			td[class=footerContainer] a[class=utilityLink]{
				display:block !important;
			}
		}
	</style>
</head>
<body leftmargin="0" marginwidth="0" topmargin="0" marginheight="0" offset="0" style="margin: 0;padding: 0;background-color: #ecf1f4;">
	<center>
		<table align="center" border="0" cellpadding="0" cellspacing="0" height="100%" width="100%" id="bodyTable" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;margin: 0;padding: 0;background-color: #ecf1f4;height: 100% !important;width: 100% !important;">
			<tr>
				<td align="center" valign="top" id="bodyCell" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;margin: 0;padding: 0;border-top: 0;height: 100% !important;width: 100% !important;">
					<!-- BEGIN TEMPLATE // -->
					<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
						<tr>
							<td align="center" valign="top" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">

							</td>
						</tr>
						<tr>
							<td align="center" valign="top" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
							</td>
						</tr>
						<tr>
							<td align="center" valign="top" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
								<!-- BEGIN BODY // -->
								<table border="0" cellpadding="0" cellspacing="0" width="100%" id="templateBody" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;background-color: #aef4fa;border-top: 0;border-bottom: 0;">
									<tr>
										<td align="center" valign="top" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
											<table border="0" cellpadding="0" cellspacing="0" width="600" class="templateContainer" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
												<tr>
													<td valign="top" class="bodyContainer" style="padding-top: 10px;padding-right: 18px;padding-bottom: 10px;padding-left: 18px;border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;"><table border="0" cellpadding="0" cellspacing="0" width="100%" class="mcnDividerBlock" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
	<tbody class="mcnDividerBlockOuter">
		<tr>
			<td class="mcnDividerBlockInner" style="padding: 20px 18px;border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
				<table class="mcnDividerContent" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
					<tbody><tr>
						<td style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
							<span></span>
						</td>
					</tr>
				</tbody></table>
			</td>
		</tr>
	</tbody>
</table><table border="0" cellpadding="0" cellspacing="0" width="100%" class="mcnImageBlock" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
	<tbody class="mcnImageBlockOuter">
			<tr>
				<td valign="top" style="padding: 0px;border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;" class="mcnImageBlockInner">
					<table align="left" width="100%" border="0" cellpadding="0" cellspacing="0" class="mcnImageContentContainer" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
						<tbody><tr>
							<td class="mcnImageContent" valign="top" style="padding-right: 0px;padding-left: 0px;padding-top: 0;padding-bottom: 0;text-align: center;border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
								<img align="center" alt="BackupBuddy" src="https://gallery.mailchimp.com/7acf83c7a47b32c740ad94a4e/images/backupbuddy_updates.png" width="350" style="float:left;max-width: 300px;padding-bottom: 0;display: inline !important;vertical-align: bottom;border: 0;line-height: 100%;outline: none;text-decoration: none;height: auto !important;" class="mcnImage">
								<h2 class="null" style="text-align: left;display: block;font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif;font-size: 24px;font-style: normal;font-weight: normal;line-height: 130%;letter-spacing: 0px;margin: 0;color: #4d7579 !important;">{heading}</h2><div style="float:left;">{site_url}</div>
							</td>
						</tr>
					</tbody></table>
				</td>
			</tr>
	</tbody>
</table><table border="0" cellpadding="0" cellspacing="0" width="100%" class="mcnTextBlock" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
	<tbody class="mcnTextBlockOuter">
		<tr>
			<td valign="top" class="mcnTextBlockInner" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">

				<table align="left" border="0" cellpadding="0" cellspacing="0" width="600" class="mcnTextContentContainer" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
					<tbody><tr>

						<td valign="top" class="mcnTextContent" style="padding-top: 9px;padding-right: 18px;padding-bottom: 9px;padding-left: 18px;border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;color: #505050;font-family: Helvetica;font-size: 14px;line-height: 150%;text-align: left;">
						</td>
					</tr>
				</tbody></table>

			</td>
		</tr>
	</tbody>
</table><table border="0" cellpadding="0" cellspacing="0" width="100%" class="mcnDividerBlock" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
	<tbody class="mcnDividerBlockOuter">
		<tr>
			<td class="mcnDividerBlockInner" style="padding: 10px 18px;border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
				<table class="mcnDividerContent" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
					<tbody><tr>
						<td style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
							<span></span>
						</td>
					</tr>
				</tbody></table>
			</td>
		</tr>
	</tbody>
</table></td>
													</tr>
												</table>
											</td>
										</tr>
									</table>
									<!-- // END BODY -->
								</td>
							</tr>
							<tr>
								<td align="center" valign="top" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
									<!-- BEGIN FOOTER // -->
									<table border="0" cellpadding="0" cellspacing="0" width="100%" id="templateFooter" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;background-color: #ffffff;border-top: 0;border-bottom: 0;">
										<tr>
											<td align="center" valign="top" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
												<table border="0" cellpadding="0" cellspacing="0" width="600" class="templateContainer" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
													<tr>
														<td valign="top" class="footerContainer" style="padding-top: 10px;padding-right: 18px;padding-bottom: 10px;padding-left: 18px;border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;"><table border="0" cellpadding="0" cellspacing="0" width="100%" class="mcnDividerBlock" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
	<tbody class="mcnDividerBlockOuter">
		<tr>
			<td class="mcnDividerBlockInner" style="padding: 10px 18px 0px;border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
				<table class="mcnDividerContent" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
					<tbody><tr>
						<td style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
							<span></span>
						</td>
					</tr>
				</tbody></table>
			</td>
		</tr>
	</tbody>
</table>



<!-- Divider -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" class="mcnDividerBlock" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
	<tbody class="mcnDividerBlockOuter">
		<tr>
			<td class="mcnDividerBlockInner" style="padding: 10px 18px;border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
				<table class="mcnDividerContent" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
					<tbody><tr>
						<td style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
							<span></span>
						</td>
					</tr>
				</tbody></table>
			</td>
		</tr>
	</tbody>
</table>


<!-- TEXT CONTENT -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" class="mcnTextBlock" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
	<tbody class="mcnTextBlockOuter">
		<tr>
			<td valign="top" class="mcnTextBlockInner" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">

				<table align="center" border="0" cellpadding="0" cellspacing="0" width="600" class="mcnTextContentContainer" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
					<tbody><tr>

						<td valign="top" class="mcnTextContent" style="padding-top: 9px;padding-right: 18px;padding-bottom: 9px;padding-left: 18px;border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;color: #505050;font-family: Helvetica;font-size: 14px;line-height: 150%;text-align: left;">

							<h1 class="null" style="display: block;font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif;font-size: 24px;font-style: normal;font-weight: bold;line-height: 150%;letter-spacing: 0px;margin: 0;text-align: center;color: #2a4143 !important;">Additional Details.</h1>
							<h4 style="text-align: center;">{body}</h4>
						</td>
					</tr>
				</tbody>
				</table>

			</td>
		</tr>
	</tbody>
</table>

<table border="0" cellpadding="0" cellspacing="0" width="100%" class="mcnButtonBlock" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;display:{support_display}!important">
	<tbody class="mcnButtonBlockOuter">
		<tr>
			<td style="padding-top: 0;padding-right: 18px;padding-bottom: 18px;padding-left: 18px;border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;" valign="top" align="center" class="mcnButtonBlockInner">
				<table border="0" cellpadding="0" cellspacing="0" class="mcnButtonContentContainer" style="border-top-left-radius: 3px;border-top-right-radius: 3px;border-bottom-right-radius: 3px;border-bottom-left-radius: 3px;background-color: #C74534;border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
					<tbody>
						<tr>
							<td align="center" valign="middle" class="mcnButtonContent" style="font-family: Arial;font-size: 20px;padding: 15px;border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;">
								<a class="mcnButton " title="Contact Support" href="http://ithemes.com/support/" target="_self" style="font-weight: bold;letter-spacing: -0.5px;line-height: 100%;text-align: center;text-decoration: none;color: #FFFFFF;word-wrap: break-word !important;">Contact Support</a>
							</td>
						</tr>
					</tbody>
				</table>
			</td>
		</tr>
	</tbody>
</table>




</td>
												</tr>
											</table>
										</td>
									</tr>
								</table>
								<!-- // END FOOTER -->
							</td>
						</tr>
					</table>
					<!-- // END TEMPLATE -->
				</td>
			</tr>
		</table>
	</center>
</body>
</html>
