<?php

// Copyright © 2007 John Perr
// Copyright © 2007-2009 Johan Cwiklinski
//
// This file is part of Galette (http://galette.tuxfamily.org).
//
// Galette is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Galette is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Galette. If not, see <http://www.gnu.org/licenses/>.

/**
 * Création des cartes d'adhérents au format PDF
 *
 * La création des cartes au format pdf se fait soit
 * - depuis la page de gestion des adhérents en sélectionnant
 *   les adhérents  dans la liste
 * - depuis la page de visualisation d'un adhérent. Une seule
 *   carte est alors générée
 *
 * Les couleurs sont définies dans l'écran des préférences
 * en utilisant des codes identiques à ceux utilisés en HTML.
 *
 * @package    Galette
 *
 * @author     John Perr <johnperr@abul.org>
 * @copyright  2007 John Perr
 * @copyright  2007-2009 Johan Cwiklinski
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version    $Id$
 * @since      Disponible depuis la Release 0.63
 */

/**
 *
 */
require_once('includes/galette.inc.php');

if( !$login->isLogged() ) {
	header("location: index.php");
	die();
}

require_once(WEB_ROOT. 'classes/pdf.class.php');
require_once (WEB_ROOT . 'classes/members.class.php');
require_once(WEB_ROOT . 'classes/varslist.class.php');
require_once(WEB_ROOT . 'classes/print_logo.class.php');

if( isset($_GET[Adherent::PK]) && is_int($_GET[Adherent::PK]) && $_GET[Adherent::PK] > 0 ) {
	// If we are called from "voir_adherent.php" get unique id value
	$unique = $_GET[Adherent::PK];
} else if( isset($_SESSION['galette']['varslist']) ){
	$varslist = unserialize( $_SESSION['galette']['varslist'] );
} else {
	$log->log('No member selected to generate members cards', PEAR_LOG_INFO);
	if( $login->isAdmin )
		header('location:gestion_adherent.php');
	else
		header('location:voir_adherent.php');
}

// Fill array $mailing_adh with selected ids
$mailing_adh = array();
if( $unique ) {
	$mailing_adh[] = $unique;
} else {
	$mailing_adh = $varslist->selected;
}

$members = Members::getArrayList($mailing_adh, 'nom_adh, prenom_adh');

if( !is_array($members) || count($members) < 1 ) {
	$log->log('An error has occured, unable to get members list.', PEAR_LOG_ERR);
	die();
}

// Set PDF headings
$doc_title    = _T("Member's Cards");
$doc_subject  = _T("Generated by Galette");
$doc_keywords = _T("Cards");

// Get fixed data from preferences
// and convert strings to utf-8 for tcpdf
$an_cot = '<strong>' . PREF_CARD_YEAR . '</strong>';
$abrev = '<strong>' . PREF_CARD_ABREV . '</b>';
$strip = PREF_CARD_STRIP;

$print_logo = new PrintLogo();
if( $logo->hasPicture() ){
	$logofile = $print_logo->getPath();

	// Set logo size to max width 30 mm or max height 25 mm
	$ratio = $print_logo->getWidth()/$print_logo->getHeight();
	if ($ratio < 1) {
		if ($print_logo->getHeight() > 16) {
			$hlogo = 25;
		} else {
			$hlogo = $print_logo->getHeight();
		}
		$wlogo = round($hlogo*$ratio);
	} else {
		if ($print_logo->getWidth() > 16) {
			$wlogo = 30;
		} else {
			$wlogo = $print_logo->getWidth();
		}
		$hlogo = round($wlogo/$ratio);
	}
}

// Create new PDF document
$pdf = new PDF("P","mm","A4",true,"UTF-8");

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor(PDF_AUTHOR);
$pdf->SetTitle($doc_title);
$pdf->SetSubject($doc_subject);
$pdf->SetKeywords($doc_keywords);

// No hearders and footers
$pdf->SetPrintHeader(false);
$pdf->SetPrintFooter(false);
$pdf->setFooterMargin(0);
$pdf->setHeaderMargin(0);

// Show full page
$pdf->SetDisplayMode("fullpage");

// Disable Auto Page breaks
$pdf->SetAutoPageBreak(false,0);

// Set colors
$pdf->SetDrawColor(160,160,160);
$pdf->SetTextColor(0);
$tcol=$pdf->colorHex2Dec(PREF_CARD_TCOL);
$scol=$pdf->colorHex2Dec(PREF_CARD_SCOL);
$bcol=$pdf->colorHex2Dec(PREF_CARD_BCOL);
$hcol=$pdf->colorHex2Dec(PREF_CARD_HCOL);

// Set margins
$pdf->SetMargins(PREF_CARD_MARGES_H, PREF_CARD_MARGES_V);

// Set font
$pdf->SetFont("FreeSerif");

// Set origin
// Top left corner
$xorigin=PREF_CARD_MARGES_H;
$yorigin=PREF_CARD_MARGES_V;

// Card width
$w = 75;
// Card heigth
$h = 40;
// Number of colons
$nbcol=2;
// Number of rows
$nbrow=6;
// Spacing betweeen cards
$hspacing=PREF_CARD_HSPACE;
$vspacing=PREF_CARD_VSPACE;

// Loop over cards
$nb_card=0;
foreach($members as $member){
	// Detect page breaks
	if ($nb_card % ($nbcol * $nbrow)==0)
		$pdf->AddPage();

	// Compute card position on page
	$col=$nb_card % $nbcol;
	$row=($nb_card/$nbcol) % $nbrow;
	// Set origin
	$x0 = $xorigin + $col*(round($w)+round($hspacing));
	$y0 = $yorigin + $row*(round($h)+round($vspacing));
	// Logo X position
	$xl = round($x0 + $w - $wlogo);
	// Get data
	$email = '<strong>';
	switch (PREF_CARD_ADDRESS){
		case 0:
			$email .= $member->email;
			break;
		case 1:
			$email .= $member->msn;
			break;
		case 2:
			$email .= $member->jabber;
			break;
		case 3:
			$email .= $member->website;
			break;
		case 4:
			$email .= $member->icq;
			break;
		case 5:
			$email .= $member->cp . ' - ' . $member->town;
			break;
		case 6:
			$email .= $member->nickname;
			break;
		case 7:
			$email .= $member->job;
			break;
	}
	$email .= '</strong>';

	// Select strip color according to status
	switch ( $member->status ){
		case  1 :
		case  2 :
		case  3 :
		case 10 :
			$fcol = $bcol;
			break;
		case  5 :
		case  6 :
			$fcol = $hcol;
			break;		default :
			$fcol = $scol;
	}

	$id = '<strong>' . $member->id_adh . '</strong>';
	$nom_adh_ext = '<strong>' . (( PREF_BOOL_DISPLAY_TITLE ) ? $member->spoliteness . ' ' : '') . $member->surname . ' ' . $member->name . '</strong>';
	$photo = $member->picture;
	$photofile = $photo->getPath();

	// Photo 100x130 and logo
	$pdf->Image($photofile,$x0,$y0,25);
	$pdf->Image($logofile,$xl,$y0,round($wlogo));

	// Color=#8C8C8C: Shadow of the year
	$pdf->SetTextColor(140);
	$pdf->SetFontSize(10);
	$pdf->SetXY($x0 + 65,$y0+$hlogo);
	$pdf->writeHTML($an_cot,false,0);

	// Colored Text (Big label, id, year)
	// Abbrev: Adapt font size to text length
	$pdf->SetTextColor($fcol["R"],$fcol["G"],$fcol["B"]);
	$fontsz = 48;
	$pdf->SetFontSize($fontsz);
	while ($pdf->GetStringWidth($abrev) > 50) {
		$fontsz--;
		$pdf->SetFontSize($fontsz);
	}
	$pdf->SetXY($x0 + 27,$y0 + 10);
	$pdf->writeHTML($abrev,false,0);

	$pdf->SetFontSize(8);
	$pdf->SetXY($x0 + 69,$y0 + 28);
	$pdf->writeHTML($id,false,0);
	$pdf->SetFontSize(10);
	$pdf->SetXY($x0 + 64.7,$y0+$hlogo - 0.3);
	$pdf->writeHTML($an_cot,false,0);

	// Name: Adapt font size to text length
	$pdf->SetTextColor(0);
	$fontsz=16;
	$pdf->SetFontSize($fontsz);
	while ($pdf->GetStringWidth($nom_adh_ext) > 50) {
		$fontsz--;
		$pdf->SetFontSize($fontsz);
	}
	$pdf->SetXY($x0 + 27,$y0 + 18);
	$pdf->writeHTML($nom_adh_ext,false,0);

	// Email (adapt too)
	$fontsz=14;
	$pdf->SetFontSize($fontsz);
	while ($pdf->GetStringWidth($email) > 50) {
		$fontsz--;
		$pdf->SetFontSize($fontsz);
	}
	$pdf->SetXY($x0 + 27,$y0 + 25);
	$pdf->writeHTML($email,false,0);

	// Lower colored strip with long text
	$pdf->SetFillColor($fcol["R"],$fcol["G"],$fcol["B"]);
	$pdf->SetTextColor($tcol["R"],$tcol["G"],$tcol["B"]);
	$pdf->SetFont("FreeSerif","B",8);
	$pdf->SetXY($x0,$y0+33);
	$pdf->Cell(75,7,$strip,0,0,"C",1);

	// Draw a gray frame around the card
	$pdf->Rect($x0,$y0,$w,$h);
	$nb_card++;
}

// Send PDF code to browser
$_SESSION['galette']['pdf_error'] = false;
$pdf->Output(_T("Cards").".pdf","D");
?>
