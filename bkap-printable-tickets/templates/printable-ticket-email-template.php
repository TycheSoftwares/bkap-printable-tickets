<?php
/**
 * Printable ticket Template
 *
 * @author 		TycheSoftwares
 * @package 	bkap-printable-tickets/
 * @version     1.0
 */ 

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

<table> 
<tbody>
<tr>
    <td width="580" valign="top" style="background:#f7f7f7;width:435.0pt;padding:15.0pt 15.0pt 15.0pt 15.0pt">
	 
	 <div align="center">  
	 <table border="0" cellspacing="0" cellpadding="0" width="100%" style="width:100.0%">
	     <tbody><tr>
	      <td width="100%" valign="top" style="width:100.0%;padding:0in 0in 0in 0in">
	      <h1 style="margin:0!important"><span style="font-size:21.0pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;;color:#0a0a0e">{{site_title}}<u></u><u></u></span></h1>
	      <p class="MsoNormal"><span style="font-size:11.5pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;">{{site_tagline}}</span> </p>
	      </td>
	     </tr>
	     <tr>
	     <td width="100%" align="left">
	     <hr noshade size=1 width="100%">
	     </td>
	     </tr>
	    </tbody>
    </table>
    </div>
    
    <p class="MsoNormal"><span><u></u><u></u></span></p>
    
    <div align="center">
	   <table border="0" cellspacing="0" cellpadding="0" width="100%" style="width:100.0%">
	     <tbody><tr>
	      <td width="100%" valign="top" style="width:100.0%;padding:0in 0in 0in 0in 0!important;margin:0!important">
	      <h1 style="margin:0!important"><span style="font-size:21.0pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;;color:#0a0a0e">{{product_name}}<u></u><u></u></span></h1>
	      </td>
	     </tr>
	    </tbody>
	   </table>
	</div>
	<div align="center">
	    <table border="0" cellspacing="0" cellpadding="0" width="100%" style="width:100.0%">
	     <tbody><tr style="height:22.5pt">
	      <td width="100%" valign="top" style="width:100.0%;background:#f7f7f7;padding:0in 0in 0in 0in;height:22.5pt;padding:0!important;margin:0!important"></td>
	     </tr>
	    </tbody>
	   </table>
    </div>
    
    <div align="center">
    <table border="0" cellspacing="0" cellpadding="0" width="100%" style="width:100.0%">
	     <tbody><tr>
	      <td width="100" valign="top" style="width:75.0pt;padding:0in 0in 0in 0in;margin:0!important">
	      <h6 style="margin-right:0in;margin-bottom:7.5pt;margin-left:0in;color:#909090!important;font-weight:700!important"><span style="font-size:10.0pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;;text-transform:uppercase">{{heading_ticket_number}}<u></u><u></u></span></h6>
	      <p class="MsoNormal"><span style="font-size:11.5pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;">{{ticket_no}}</span>
	      </p>
	      </td>
	      <td width="120" valign="top" style="width:1.25in;padding:0in 0in 0in 0in;margin:0!important">
	      <h6 style="margin-right:0in;margin-bottom:7.5pt;margin-left:0in;color:#909090!important;font-weight:700!important"><span style="font-size:10.0pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;;text-transform:uppercase">{{headings_booking_details}}<u></u><u></u></span></h6>
	      <p class="MsoNormal"><span style="font-size:11.5pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;">{{product_name}}</span> </p>
	      <p class="MsoNormal"><span style="font-size:11.5pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;">{{booking}}</span> </p>
	      <p class="MsoNormal"><span style="font-size:11.5pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;">{{addon}}</span> </p>
	      </td>
	      <td width="120" valign="top" style="width:1.25in;padding:0in 0in 0in 0in 0!important;margin:0!important">
	      <h6 style="margin-right:0in;margin-bottom:7.5pt;margin-left:0in;color:#909090!important;font-weight:700!important"><span style="font-size:10.0pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;;text-transform:uppercase">{{headings_buyer}}<u></u><u></u></span></h6>
	      <p class="MsoNormal"><span style="font-size:11.5pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;">{{buyers_firstname}} {{buyers_lastname}}</span> </p>
	      </td>
	      <td width="120" valign="top" style="width:1.25in;padding:0in 0in 0in 0in;margin:0!important">
	      <h6 style="margin-right:0in;margin-bottom:7.5pt;margin-left:0in;color:#909090!important;font-weight:700!important"><span style="font-size:10.0pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;;text-transform:uppercase">{{headings_security_code}}<u></u><u></u></span></h6>
	      <p class="MsoNormal"><span style="font-size:11.5pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;">{{security_unique_no}}</span>
	      </p>
	      </td>
	     </tr>
	    </tbody>
    </table>
    </div>
    
    <table border="0" cellspacing="0" cellpadding="0" width="100%" style="width:100.0%">
	     <tbody><tr style="height:22.5pt">
	      <td width="100%" valign="top" style="width:100.0%;background:#f7f7f7;padding:0in 0in 0in 0in;height:22.5pt;padding:0!important;margin:0!important"></td>
	     </tr>
	    </tbody>
    </table>
      
    <div align="center">
    <table border="0" cellspacing="0" cellpadding="0" width="100%" style="width:100.0%">
	     <tbody><tr>
	      <td width="100%" valign="top" style="width:100.0%;padding:0in 0in 0in 0in 0!important;margin:0!important">
	      <p class="MsoNormal"><a href="'.$site_url.'" target="_blank"><span style="font-size:10.0pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;">{{site_url}}</span></a>
	      </td>
	     </tr>
	    </tbody>
    </table>
    </div>
    </td>
   </tr>
   </table>