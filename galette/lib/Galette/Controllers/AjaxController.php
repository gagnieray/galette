<?php

/**
 * Copyright © 2003-2025 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
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
 */

declare(strict_types=1);

namespace Galette\Controllers;

use Analog\Analog;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Filters\MembersList;
use Galette\Repository\Members;
use Galette\Util\Password;
use Galette\Util\Telemetry;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Throwable;

/**
 * Galette ajax controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class AjaxController extends AbstractController
{
    /**
     * Messages as JSON array
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function messages(Request $request, Response $response): Response
    {
        $messages = [];

        $errors = $this->flash->getMessage('loginfault') ?? [];
        $errors = array_merge($errors, $this->flash->getMessage('error_detected') ?? []);
        $errors = array_merge($errors, $this->flash->getMessage('error') ?? []);

        if (count($errors) > 0) {
            $messages['error'] = [
                'title' => _T('- ERROR -'),
                'icon' => 'exclamation circle',
                'messages' => $errors
            ];
        }

        $warnings = $this->flash->getMessage('warning_detected') ?? [];
        $warnings = array_merge($warnings, $this->flash->getMessage('warning') ?? []);

        if (count($warnings) > 0) {
            $messages['warning'] = [
                'title' => _T('- WARNING -'),
                'icon' => 'exclamation triangle',
                'messages' => $warnings
            ];
        }

        $info = $this->flash->getMessage('info_detected') ?? [];
        $info = array_merge($info, $this->flash->getMessage('info') ?? []);

        if (count($info) > 0) {
            $messages['info'] = [
                'title' => '',
                'icon' => 'info',
                'messages' => $info
            ];
        }

        $success = $this->flash->getMessage('success_detected') ?? [];
        $success = array_merge($success, $this->flash->getMessage('succes') ?? []);

        if (count($success) > 0) {
            $messages['success'] = [
                'title' => '',
                'icon' => 'check circle outline',
                'messages' => $success
            ];
        }

        return $this->withJson($response, $messages);
    }

    /**
     * Ajax town suggestion
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param string   $term     Search term
     *
     * @return Response
     */
    public function suggestTowns(Request $request, Response $response, string $term): Response
    {
        $ret = [];

        try {
            $select1 = $this->zdb->select(Adherent::TABLE);
            $select1->columns(['ville_adh']);
            $select1->where->like('ville_adh', '%' . html_entity_decode($term) . '%');

            $select2 = $this->zdb->select(Adherent::TABLE);
            $select2->columns(['lieu_naissance']);
            $select2->where->like('lieu_naissance', '%' . html_entity_decode($term) . '%');

            $select1->combine($select2);

            $select = $this->zdb->sql->select();
            $select->from(['sub' => $select1])
                ->order('ville_adh ASCC')
                ->limit(10);

            $towns = $this->zdb->execute($select);

            $ret['success'] = true;
            $ret['results'] = [];
            foreach ($towns as $town) {
                $ret['results'][] = [
                    'title' => $town->ville_adh
                ];
            }
        } catch (Throwable $e) {
            Analog::log(
                'Something went wrong in towns suggestion: ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }

        return $this->withJson($response, $ret);
    }

    /**
     * Ajax countries suggestion
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param string   $term     Search term
     *
     * @return Response
     */
    public function suggestCountries(Request $request, Response $response, string $term): Response
    {
        $ret = [];

        try {
            $select = $this->zdb->select(Adherent::TABLE);
            $select->columns(['pays_adh']);
            $select->where->like('pays_adh', '%' . html_entity_decode($term) . '%');
            $select->limit(10);
            $select->order(['pays_adh ASC']);

            $countries = $this->zdb->execute($select);

            $ret['success'] = true;
            $ret['results'] = [];
            foreach ($countries as $country) {
                $ret['results'][] = [
                    'title' => $country->pays_adh
                ];
            }
        } catch (Throwable $e) {
            Analog::log(
                'Something went wrong in countries suggestion: ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }

        return $this->withJson($response, $ret);
    }

    /**
     * Ajax regions suggestion
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param string   $term     Search term
     *
     * @return Response
     */
    public function suggestRegions(Request $request, Response $response, string $term): Response
    {
        $ret = [];

        try {
            $select = $this->zdb->select(Adherent::TABLE);
            $select->columns(['region_adh']);
            $select->where->like('region_adh', '%' . html_entity_decode($term) . '%');
            $select->limit(10);
            $select->order(['region_adh ASC']);

            $regions = $this->zdb->execute($select);

            $ret['success'] = true;
            $ret['results'] = [];
            foreach ($regions as $region) {
                $ret['results'][] = [
                    'title' => $region->region_adh
                ];
            }
        } catch (Throwable $e) {
            Analog::log(
                'Something went wrong in regions suggestion: ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }

        return $this->withJson($response, $ret);
    }

    /**
     * Telemetry info preview
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function telemetryInfos(Request $request, Response $response): Response
    {
        $telemetry = new Telemetry(
            $this->zdb,
            $this->preferences,
            $this->plugins
        );
        $body = $response->getBody();
        $body->write('<pre>' . json_encode($telemetry->getTelemetryInfos(), JSON_PRETTY_PRINT) . '</pre>');
        return $response;
    }

    /**
     * Send telemetry info
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function telemetrySend(Request $request, Response $response): Response
    {
        $telemetry = new Telemetry(
            $this->zdb,
            $this->preferences,
            $this->plugins
        );
        try {
            $telemetry->send();
            $message = _T('Telemetry information has been sent. Thank you!');
            $result = [
                'success'   => true,
                'message'   => $message
            ];
        } catch (Throwable $e) {
            $result = [
                'success'   => false,
                'message'   => $e->getMessage()
            ];
        }
        return $this->withJson($response, $result);
    }

    /**
     * Successful telemetry registration
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function telemetryRegistered(Request $request, Response $response): Response
    {
        $this->preferences->updateRegistrationDate();
        return $this->withJson($response, ['message' => _T('Thank you for registering!')]);
    }

    /**
     * Contributions dates
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function contributionDates(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();

        $contrib = new Contribution(
            $this->zdb,
            $this->login,
            [
                'type'  => (int)$post['fee_id'],
                'adh'   => (int)$post['member_id']
            ]
        );

        return $this->withJson(
            $response,
            [
                'date_debut_cotis'  => $contrib->begin_date,
                'date_fin_cotis'    => $contrib->end_date
            ]
        );
    }

    /**
     * Contributions dates
     *
     * @param Request     $request  PSR Request
     * @param Response    $response PSR Response
     * @param int|null    $page     Page number
     * @param string|null $search   Search string
     *
     * @return Response
     */
    public function contributionMembers(Request $request, Response $response, ?int $page = null, ?string $search = null): Response
    {
        $post = $request->getParsedBody();
        $filters = new MembersList();
        if (isset($post['page'])) {
            $filters->current_page = (int)$post['page'];
        } elseif ($page !== null) {
            $filters->current_page = $page;
        }

        if (isset($post['search'])) {
            $search = $post['search'];
        }
        if ($search !== null) {
            $filters->filter_str = $search;
            if (is_numeric($search)) {
                $filters->field_filter = Members::FILTER_ID;
            }
        }

        $m = new Members($filters);
        $list_members = $m->getDropdownMembers($this->zdb, $this->login);

        $members = [];
        if (count($list_members) > 0) {
            foreach ($list_members as $pk => $member) {
                $members[] = [
                    'name'  => $member,
                    'value' => $pk
                ];
            }
        }

        return $this->withJson(
            $response,
            [
                'results'   => $members
            ]
        );
    }

    /**
     * Password strength
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function passwordStrength(Request $request, Response $response): Response
    {
        //post params may be passed from security tab test password
        $post = $request->getParsedBody();

        if (isset($post['pref_password_length'])) {
            $this->preferences->pref_password_length = $post['pref_password_length'];
        }

        if (isset($post['pref_password_strength'])) {
            $this->preferences->pref_password_strength = $post['pref_password_strength'];
        }

        if (isset($post['pref_password_blacklist'])) {
            $this->preferences->pref_password_blacklist = $post['pref_password_blacklist'];
        }

        $pass = new Password($this->preferences);
        $valid = $pass->isValid($post['value']);

        return $this->withJson(
            $response,
            [
                'valid'     => $valid,
                'score'     => $pass->getStrenght(),
                'errors'    => $pass->getErrors(),
                'warnings'  => ($valid ? $pass->getStrenghtErrors() : null)
            ]
        );
    }
}
