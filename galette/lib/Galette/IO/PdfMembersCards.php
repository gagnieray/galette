<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Member card PDF
 *
 * PHP version 5
 *
 * Copyright © 2021 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  IO
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.8.2dev - 2014-11-30
 */

namespace Galette\IO;

use Galette\Core\Preferences;
use Galette\Core\PrintLogo;
use Analog\Analog;

/**
 * Member card PDF
 *
 * @category  IO
 * @name      PDF
 * @package   Galette
 * @abstract  Class for expanding TCPDF.
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.8.2dev - 2014-11-30
 */

class PdfMembersCards extends Pdf
{
    public const WIDTH = 75;
    public const HEIGHT = 40;
    public const COLS = 2;
    public const ROWS = 6;

    private $tcol;
    private $scol;
    private $bcol;
    private $hcol;
    private $xorigin;
    private $yorigin;
    private $wi;
    private $he;
    private $nbcol;
    private $nbrow;
    private $hspacing;
    private $vspacing;
    private $max_text_size;
    private $year_font_size;
    private $an_cot;
    private $abrev;
    private $wlogo;
    private $hlogo;
    private $logofile;

    /**
     * Main constructor, set creator and author
     *
     * @param Preferences $prefs Preferences
     */
    public function __construct(Preferences $prefs)
    {
        parent::__construct($prefs);
        $this->setRTL(false);
        $this->filename = __('cards') . '.pdf';
        $this->init();
    }
    /**
     * Initialize PDF
     *
     * @return void
     */
    private function init()
    {
        // Set document information
        $this->SetTitle(_T("Member's Cards"));
        $this->SetSubject(_T("Generated by Galette"));
        $this->SetKeywords(_T("Cards"));

        // No hearders and footers
        $this->SetPrintHeader(false);
        $this->SetPrintFooter(false);
        $this->setFooterMargin(0);
        $this->setHeaderMargin(0);

        // Show full page
        $this->SetDisplayMode('fullpage');

        // Disable Auto Page breaks
        $this->SetAutoPageBreak(false, 0);

        // Set colors
        $this->SetDrawColor(160, 160, 160);
        $this->SetTextColor(0);
        $this->tcol = $this->colorHex2Dec($this->preferences->pref_card_tcol);
        $this->scol = $this->colorHex2Dec($this->preferences->pref_card_scol);
        $this->bcol = $this->colorHex2Dec($this->preferences->pref_card_bcol);
        $this->hcol = $this->colorHex2Dec($this->preferences->pref_card_hcol);

        // Set margins
        $this->SetMargins(
            $this->preferences->pref_card_marges_h,
            $this->preferences->pref_card_marges_v
        );

        // Set font
        $this->SetFont(self::FONT);

        // Set origin
        // Top left corner
        $this->xorigin = $this->preferences->pref_card_marges_h;
        $this->yorigin = $this->preferences->pref_card_marges_v;

        // Card width
        $this->wi = self::getWidth();
        // Card heigth
        $this->he = self::getHeight();
        // Number of colons
        $this->nbcol = self::getCols();
        // Number of rows
        $this->nbrow = self::getRows();
        // Spacing between cards
        $this->hspacing = $this->preferences->pref_card_hspace;
        $this->vspacing = $this->preferences->pref_card_vspace;

        //maximum size for visible text. May vary with fonts.
        $this->max_text_size = 80;
        $this->year_font_size = 8;

        // Get fixed data from preferences
        $this->an_cot = $this->preferences->pref_card_year;
        $this->abrev = $this->preferences->pref_card_abrev;

        $print_logo = new PrintLogo();
        $this->logofile = $print_logo->getPath();

        // Set logo size to max width 30mm (113px) or max height 17mm (64px)
        $ratio = $print_logo->getWidth() / $print_logo->getHeight();
        if ($ratio < 1.71) {
            if ($print_logo->getHeight() > 64) {
                $this->hlogo = 17;
            } else {
                // Convert original pixels size to millimeters
                $this->hlogo = $print_logo->getHeight() / 3.78;
            }
            $this->wlogo = round($this->hlogo * $ratio);
        } else {
            if ($print_logo->getWidth() > 113) {
                $this->wlogo = 30;
            } else {
                // Convert original pixels size to millimeters
                $this->wlogo = $print_logo->getWidth() / 3.78;
            }
            $this->hlogo = round($this->wlogo / $ratio);
        }
    }

    /**
     * Draw members cards
     *
     * @param array $members Members
     *
     * @return void
     */
    public function drawCards($members)
    {
        $nb_card = 0;
        foreach ($members as $member) {
            // Detect page breaks
            if ($nb_card % ($this->nbcol * $this->nbrow) == 0) {
                $this->AddPage();
            }

            // Compute card position on page
            $col = $nb_card % $this->nbcol;
            $row = (int)(($nb_card / $this->nbcol)) % $this->nbrow;
            // Set origin
            $x0 = $this->xorigin + $col * (round($this->wi) + round($this->hspacing));
            $y0 = $this->yorigin + $row * (round($this->he) + round($this->vspacing));
            // Logo X position
            $xl = round($x0 + $this->wi - $this->wlogo);
            // Get data
            $email = '';
            switch ($this->preferences->pref_card_address) {
                case 0:
                    $email .= $member->email;
                    break;
                case 5:
                    $email .= $member->zipcode . ' - ' . $member->town;
                    break;
                case 6:
                    $email .= $member->nickname;
                    break;
                case 7:
                    $email .= $member->job;
                    break;
                case 8:
                    $email .= $member->number;
                    break;
            }

            // Select strip color according to status
            switch ($member->status) {
                case 1:
                case 2:
                case 3:
                case 10:
                    $fcol = $this->bcol;
                    break;
                case 5:
                case 6:
                    $fcol = $this->hcol;
                    break;
                default:
                    $fcol = $this->scol;
            }

            $nom_adh_ext = '';
            if ($this->preferences->pref_bool_display_title) {
                $nom_adh_ext .= $member->stitle;
            }
            $nom_adh_ext .= $member->sname;
            $photo = $member->picture;
            $photofile = $photo->getPath();

            // Photo 100x130 and logo
            $this->Image($photofile, $x0, $y0, 25);
            $this->Image($this->logofile, $xl, $y0, round($this->wlogo));

            // Color=#8C8C8C: Shadow of the year
            $this->SetTextColor(140);
            $this->SetFontSize($this->year_font_size);

            $an_cot = $this->an_cot;
            if ($an_cot === 'DEADLINE') {
                //get current member deadline
                $an_cot = $member->due_date;
            }

            $xan_cot = $x0 + $this->wi - $this->GetStringWidth(
                $an_cot,
                self::FONT,
                'B',
                $this->year_font_size
            ) - 0.2;
            $this->SetXY($xan_cot, $y0 + $this->hlogo - 0.3);
            $this->writeHTML('<strong>' . $an_cot . '</strong>', false, false);

            // Colored Text (Big label, id, year)
            $this->SetTextColor($fcol['R'], $fcol['G'], $fcol['B']);

            $this->SetFontSize(8);

            if (!empty($this->preferences->pref_show_id) || !empty($member->number)) {
                $member_id = (!empty($member->number)) ? $member->number : $member->id;
                $xid = $x0 + $this->wi - $this->GetStringWidth($member_id, self::FONT, 'B', 8) - 0.2;
                $this->SetXY($xid, $y0 + 28);
                $this->writeHTML('<strong>' . $member_id . '</strong>', false, false);
            }
            $this->SetFontSize($this->year_font_size);
            $xan_cot = $xan_cot - 0.3;
            $this->SetXY($xan_cot, $y0 + $this->hlogo - 0.3);
            $this->writeHTML('<strong>' . $an_cot . '</strong>', false, false);

            // Abbrev: Adapt font size to text length
            $this->fixSize(
                $this->abrev,
                $this->max_text_size,
                12,
                'B'
            );
            $this->SetXY($x0 + 27, $y0 + 12);
            $this->writeHTML('<strong>' . $this->abrev . '</strong>', true, false);

            // Name: Adapt font size to text length
            $this->SetTextColor(0);
            $this->fixSize(
                $nom_adh_ext,
                $this->max_text_size,
                8,
                'B'
            );
            $this->SetXY($x0 + 27, $this->getY() + 4);
            //$this->setX($x0 + 27);
            $this->writeHTML('<strong>' . $nom_adh_ext . '</strong>', true, false);

            // Email (adapt too)
            $this->fixSize(
                $email,
                $this->max_text_size,
                6,
                'B'
            );
            $this->setX($x0 + 27);
            $this->writeHTML('<strong>' . $email . '</strong>', false, false);

            // Lower colored strip with long text
            $this->SetFillColor($fcol['R'], $fcol['G'], $fcol['B']);
            $this->SetTextColor(
                $this->tcol['R'],
                $this->tcol['G'],
                $this->tcol['B']
            );
            $this->SetFont(self::FONT, 'B', 6);
            $this->SetXY($x0, $y0 + 33);
            $this->Cell(
                $this->wi,
                7,
                $this->preferences->pref_card_strip,
                0,
                0,
                'C',
                true
            );

            // Draw a gray frame around the card
            $this->Rect($x0, $y0, $this->wi, $this->he);
            $nb_card++;
        }
    }

    /**
     * Get card width
     *
     * @return integer
     */
    public static function getWidth()
    {
        return defined('GALETTE_CARD_WIDTH') ? GALETTE_CARD_WIDTH : self::WIDTH;
    }

    /**
     * Get card height
     *
     * @return integer
     */
    public static function getHeight()
    {
        return defined('GALETTE_CARD_HEIGHT') ? GALETTE_CARD_HEIGHT : self::HEIGHT;
    }

    /**
     * Get number of columns
     *
     * @return integer
     */
    public static function getCols()
    {
        return defined('GALETTE_CARD_COLS') ? GALETTE_CARD_COLS : self::COLS;
    }

    /**
     * Get number of rows
     *
     * @return integer
     */
    public static function getRows()
    {
        return defined('GALETTE_CARD_ROWS') ? GALETTE_CARD_ROWS : self::ROWS;
    }
}
