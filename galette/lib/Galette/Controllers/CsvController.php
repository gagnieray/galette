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

use Galette\Filters\ContributionsList;
use Galette\Filters\ScheduledPaymentsList;
use Galette\IO\ContributionsCsv;
use Galette\IO\ScheduledPaymentsCsv;
use Laminas\Db\ResultSet\ResultSet;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Galette\Entity\ImportModel;
use Galette\Filters\MembersList;
use Galette\IO\Csv;
use Galette\IO\CsvIn;
use Galette\IO\CsvOut;
use Galette\IO\MembersCsv;
use Galette\Repository\DynamicFieldsSet;
use Analog\Analog;
use Slim\Psr7\Stream;

/**
 * Galette CSV controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class CsvController extends AbstractController
{
    /**
     * Send response
     *
     * @param Response $response PSR Response
     * @param string   $filepath File path on disk
     * @param string   $filename File name for output
     *
     * @return Response
     */
    protected function sendResponse(Response $response, string $filepath, string $filename): Response
    {
        if (file_exists($filepath)) {
            $response = $response->withHeader('Content-Description', 'File Transfer')
                ->withHeader('Content-Type', 'text/csv')
                ->withHeader('Content-Disposition', 'attachment;filename="' . $filename . '"')
                ->withHeader('Pragma', 'no-cache')
                ->withHeader('Content-Transfer-Encoding', 'binary')
                ->withHeader('Expires', '0')
                ->withHeader('Cache-Control', 'must-revalidate')
                ->withHeader('Pragma', 'public');

            $stream = fopen('php://memory', 'r+');
            fwrite($stream, file_get_contents($filepath));
            rewind($stream);

            return $response->withBody(new Stream($stream));
        } else {
            Analog::log(
                'A request has been made to get a CSV file named `' .
                $filename . '` that does not exists (' . $filepath . ').',
                Analog::WARNING
            );
            //FIXME: use a proper error page
            return $response->withStatus(404);
        }
    }

    /**
     * Exports page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function export(Request $request, Response $response): Response
    {
        $csv = new CsvOut();

        $tables_list = $this->zdb->getTables();
        $parameted = $csv->getParametedExports();
        $existing = $csv->getExisting();

        // display page
        $this->view->render(
            $response,
            'pages/export.html.twig',
            array(
                'page_title'        => _T("Exports"),
                'tables_list'       => $tables_list,
                'written'           => $this->flash->getMessage('written_exports'),
                'existing'          => $existing,
                'parameted'         => $parameted,
                'documentation'     => 'usermanual/avancee.html#csv-exports'
            )
        );
        return $response;
    }

    /**
     * Proceed exports
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function doExport(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $csv = new CsvOut();
        $written = [];

        if (isset($post['export_tables']) && $post['export_tables'] != '') {
            foreach ($post['export_tables'] as $table) {
                $select = $this->zdb->sql->select($table);
                $results = $this->zdb->execute($select);

                if ($results->count() > 0) {
                    $filename = $table . '_full.csv';
                    $filepath = CsvOut::DEFAULT_DIRECTORY . $filename;
                    $fp = fopen($filepath, 'w');
                    if ($fp) {
                        $csv->export(
                            $results,
                            Csv::DEFAULT_SEPARATOR,
                            Csv::DEFAULT_QUOTE,
                            true,
                            $fp
                        );
                        fclose($fp);
                        $written[] = [
                            'name' => $table,
                            'file' => $filename
                        ];
                    }
                } else {
                    $this->flash->addMessage(
                        'warning_detected',
                        str_replace(
                            '%table',
                            $table,
                            _T("Table %table is empty, and has not been exported.")
                        )
                    );
                }
            }
        }

        if (isset($post['export_parameted']) && $post['export_parameted'] != '') {
            foreach ($post['export_parameted'] as $p) {
                $res = $csv->runParametedExport($p);
                $pn = $csv->getParamedtedExportName($p);
                switch ($res) {
                    case Csv::FILE_NOT_WRITABLE:
                        $this->flash->addMessage(
                            'error_detected',
                            str_replace(
                                '%export',
                                $pn,
                                _T("Export file could not be write on disk for '%export'. Make sure web server can write in the exports directory.")
                            )
                        );
                        break;
                    case Csv::DB_ERROR:
                        $this->flash->addMessage(
                            'error_detected',
                            str_replace(
                                '%export',
                                $pn,
                                _T("An error occurred running parameted export '%export'.")
                            )
                        );
                        break;
                    case false:
                        $this->flash->addMessage(
                            'error_detected',
                            str_replace(
                                '%export',
                                $pn,
                                _T("An error occurred running parameted export '%export'. Please check the logs.")
                            )
                        );
                        break;
                    default:
                        //no error, file has been written to disk
                        $written[] = [
                            'name' => $pn,
                            'file' => (string)$res
                        ];
                        break;
                }
            }
        }

        if (count($written)) {
            foreach ($written as $ex) {
                $path = $this->routeparser->urlFor('getCsv', ['type' => 'export', 'file' => $ex['file']]);
                $this->flash->addMessage(
                    'written_exports',
                    '<a href="' . $path . '">' . $ex['name'] . ' (' . $ex['file'] . ')</a>'
                );
            }
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('export'));
    }

    /**
     * Imports page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function import(Request $request, Response $response): Response
    {
        $csv = new CsvIn($this->zdb);
        $existing = $csv->getExisting();

        // display page
        $this->view->render(
            $response,
            'pages/import.html.twig',
            array(
                'page_title'        => _T("Imports"),
                'existing'          => $existing,
                'dryrun'            => true,
                'import_file'       => $this->session->import_file,
                'documentation'     => 'usermanual/adherents.html#csv-imports'
            )
        );
        return $response;
    }

    /**
     * Proceed imports
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function doImports(Request $request, Response $response): Response
    {
        $csv = new CsvIn($this->zdb);
        $post = $request->getParsedBody();
        $dryrun = isset($post['dryrun']);

        //store selected file to dispaly again in UI
        $this->session->import_file = $post['import_file'];

        $res = $csv->import(
            $this->zdb,
            $this->preferences,
            $this->history,
            $post['import_file'],
            $this->members_fields,
            $this->members_fields_cats,
            $dryrun
        );
        if ($res !== true) {
            if ($res < 0) {
                $this->flash->addMessage(
                    'error_detected',
                    $csv->getErrorMessage($res)
                );
                if (count($csv->getErrors()) > 0) {
                    foreach ($csv->getErrors() as $error) {
                        $this->flash->addMessage(
                            'error_detected',
                            $error
                        );
                    }
                }
            } else {
                $this->flash->addMessage(
                    'error_detected',
                    _T("An error occurred importing the file :(")
                );
            }
        } else {
            if ($this->session->import_file && !$dryrun) {
                $this->session->import_file = null;
            }
            $this->flash->addMessage(
                'success_detected',
                str_replace(
                    '%filename%',
                    $post['import_file'],
                    _T("File '%filename%' has been successfully imported :)")
                )
            );
        }
        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('import'));
    }

    /**
     * Get CSV file (imports or exports)
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function uploadImportFile(Request $request, Response $response): Response
    {
        $csv = new CsvIn($this->zdb);
        if (isset($_FILES['new_file'])) {
            if ($_FILES['new_file']['error'] === UPLOAD_ERR_OK) {
                if ($_FILES['new_file']['tmp_name'] != '') {
                    if (is_uploaded_file($_FILES['new_file']['tmp_name'])) {
                        $res = $csv->store($_FILES['new_file']);
                        if ($res < 0) {
                            $this->flash->addMessage(
                                'error_detected',
                                $csv->getErrorMessage($res)
                            );
                        } else {
                            $this->flash->addMessage(
                                'success_detected',
                                _T("Your file has been successfully uploaded!")
                            );
                        }
                    }
                }
            } elseif ($_FILES['new_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                Analog::log(
                    $csv->getPhpErrorMessage($_FILES['new_file']['error']),
                    Analog::WARNING
                );
                $this->flash->addMessage(
                    'error_detected',
                    $csv->getPhpErrorMessage(
                        $_FILES['new_file']['error']
                    )
                );
            } elseif (isset($_POST['upload'])) {
                $this->flash->addMessage(
                    'error_detected',
                    _T("No files has been seleted for upload!")
                );
            }
        } else {
            $this->flash->addMessage(
                'warning_detected',
                _T("No files has been uploaded!")
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('import'));
    }

    /**
     * Get CSV file (imports or exports)
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param string   $file     File name
     * @param string   $type     File type
     *
     * @return Response
     */
    public function getFile(Request $request, Response $response, string $file, string $type): Response
    {
        $filename = $file;

        //Exports main contain user confidential data, they're accessible only for
        //admins or staff members
        if ($this->login->isAdmin() || $this->login->isStaff()) {
            $filepath = $type === 'export' ?
                CsvOut::DEFAULT_DIRECTORY : CsvIn::DEFAULT_DIRECTORY;
            $filepath .= $filename;
            return $this->sendResponse($response, $filepath, $filename);
        } else {
            Analog::log(
                'A non authorized person asked to retrieve ' . $type . ' file named `' .
                $filename . '`. Access has not been granted.',
                Analog::WARNING
            );
            return $response->withStatus(403);
        }
    }

    /**
     * Remove CSV file confirmation (imports or exports)
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param string   $file     File name
     * @param string   $type     File type
     *
     * @return Response
     */
    public function confirmRemoveFile(
        Request $request,
        Response $response,
        string $file,
        string $type
    ): Response {
        $data = [
            'type' => $type,
            'file' => $file,
            'redirect_uri'  => $this->routeparser->urlFor($type)
        ];

        // display page
        $this->view->render(
            $response,
            'modals/confirm_removal.html.twig',
            array(
                'mode'          => ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') ? 'ajax' : '',
                'page_title'    => sprintf(
                    _T('Remove %1$s file %2$s'),
                    $type,
                    $file
                ),
                'form_url'      => $this->routeparser->urlFor(
                    'doRemoveCsv',
                    [
                        'type' => $type,
                        'file' => $file
                    ]
                ),
                'cancel_uri'    => $data['redirect_uri'],
                'data'          => $data
            )
        );
        return $response;
    }

    /**
     * Remove CSV file (imports or exports)
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param string   $file     File name
     * @param string   $type     File type
     *
     * @return Response
     */
    public function removeFile(Request $request, Response $response, string $file, string $type): Response
    {
        $post = $request->getParsedBody();
        $ajax = isset($post['ajax']) && $post['ajax'] === 'true';
        $success = false;

        $uri = $post['redirect_uri'] ?? $this->routeparser->urlFor('slash');

        if (!isset($post['confirm'])) {
            $this->flash->addMessage(
                'error_detected',
                _T("Removal has not been confirmed!")
            );
        } else {
            $csv = $type === 'export' ?
                new CsvOut() : new CsvIn($this->zdb);
            $res = $csv->remove($file);
            if ($res === true) {
                $success = true;
                $this->flash->addMessage(
                    'success_detected',
                    str_replace(
                        '%export',
                        $file,
                        _T("'%export' file has been removed from disk.")
                    )
                );
            } else {
                $success = false;
                $this->flash->addMessage(
                    'error_detected',
                    str_replace(
                        '%export',
                        $file,
                        _T("Cannot remove '%export' from disk :/")
                    )
                );
            }
        }

        if (!$ajax) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $uri);
        } else {
            return $this->withJson(
                $response,
                [
                    'success'   => $success
                ]
            );
        }
    }

    /**
     * Import model page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function importModel(Request $request, Response $response): Response
    {
        $model = new ImportModel();
        $model->load();

        if (isset($request->getQueryParams()['remove'])) {
            $model->remove($this->zdb);
            $model->load();
        }

        $csv = new CsvIn($this->zdb);

        /** FIXME:
         * - set fields that should not be part of import
         */
        $fields = $model->getFields();
        $defaults = $csv->getDefaultFields();
        $defaults_loaded = false;

        if ($fields === null) {
            $fields = $defaults;
            $defaults_loaded = true;
        }

        $import_fields = $this->members_form_fields;
        //get dynamic fields
        $dynamic_import_fields = [];
        $fieldset = new DynamicFieldsSet($this->zdb, $this->login);
        $dfields = $fieldset->getList('adh');
        foreach ($dfields as $field) {
            if ($field->hasData() && !$field instanceof \Galette\DynamicFields\File) {
                $dynamic_import_fields['dynfield_' . $field->getId()] = [
                    'label'     => __($field->getname())
                ];
            }
        }
        //we do not want to import id_adh. Never.
        unset($import_fields['id_adh']);
        $import_fields += $dynamic_import_fields;

        //Active tab on page
        $tab = $request->getQueryParams()['tab'] ?? 'current';

        // display page
        $this->view->render(
            $response,
            'pages/import_model.html.twig',
            array(
                'page_title'        => _T("CSV import model"),
                'fields'            => $fields,
                'model'             => $model,
                'defaults'          => $defaults,
                'members_fields'    => $import_fields,
                'defaults_loaded'   => $defaults_loaded,
                'tab'               => $tab,
                'documentation'     => 'usermanual/adherents.html#model'
            )
        );
        return $response;
    }

    /**
     * Get CSV import model file
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function getImportModel(Request $request, Response $response): Response
    {
        $model = new ImportModel();
        $model->load();

        $csv = new CsvIn($this->zdb);

        $fields = $model->getFields();
        $defaults = $csv->getDefaultFields();

        if ($fields === null) {
            $fields = $defaults;
        }

        $ocsv = new CsvOut();
        $res = $ocsv->export(
            new ResultSet(),
            Csv::DEFAULT_SEPARATOR,
            Csv::DEFAULT_QUOTE,
            $fields
        );
        $filename = _T("galette_import_model.csv");

        $response = $response->withHeader('Content-Description', 'File Transfer')
            ->withHeader('Content-Type', 'text/csv')
            ->withHeader('Content-Disposition', 'attachment;filename="' . $filename . '"')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Content-Transfer-Encoding', 'binary')
            ->withHeader('Expires', '0')
            ->withHeader('Cache-Control', 'must-revalidate')
            ->withHeader('Pragma', 'public');

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $res);
        rewind($stream);

        return $response->withBody(new Stream($stream));
    }

    /**
     * Store CSV model
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function storeModel(Request $request, Response $response): Response
    {
        $model = new ImportModel();
        $model->load();

        $model->setFields($request->getParsedBody()['fields']);
        $res = $model->store($this->zdb);
        if ($res === true) {
            $this->flash->addMessage(
                'success_detected',
                _T("Import model has been successfully stored :)")
            );
        } else {
            $this->flash->addMessage(
                'error_detected',
                _T("Import model has not been stored :(")
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('importModel'));
    }

    /**
     * Members CSV exports
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function membersExport(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $get = $request->getQueryParams();

        $session_var = $post['session_var'] ?? $get['session_var'] ?? $this->getFilterName(Crud\MembersController::getDefaultFilterName());

        if (isset($this->session->$session_var)) {
            $filters = $this->session->$session_var;
        } else {
            $filters = new MembersList();
        }

        $csv = new MembersCsv(
            $this->zdb,
            $this->login,
            $this->members_fields,
            $this->fields_config
        );
        $csv->exportMembers($filters);

        $filepath = $csv->getPath();
        $filename = $csv->getFileName();

        return $this->sendResponse($response, $filepath, $filename);
    }

    /**
     * Contributions CSV exports
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param string   $type     One of 'contributions' or 'transactions'
     *
     * @return Response
     */
    public function contributionsExport(Request $request, Response $response, string $type): Response
    {
        $post = $request->getParsedBody();
        $get = $request->getQueryParams();

        $session_var = $post['session_var'] ?? $get['session_var'] ?? $this->getFilterName($type, ['suffix' => 'csvexport']);

        if (isset($this->session->$session_var)) {
            $filters = $this->session->$session_var;
        } else {
            $filters = new ContributionsList();
        }

        $csv = new ContributionsCsv(
            $this->zdb,
            $this->login,
            $type
        );
        $csv->exportContributions($filters);

        $filepath = $csv->getPath();
        $filename = $csv->getFileName();

        return $this->sendResponse($response, $filepath, $filename);
    }

    /**
     * Scheduled payments CSV exports
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function scheduledPaymentsExport(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $get = $request->getQueryParams();

        $session_var = $post['session_var'] ?? $get['session_var'] ?? $this->getFilterName(Crud\ScheduledPaymentController::getDefaultFilterName(), ['suffix' => 'csvexport']);

        if (isset($this->session->$session_var)) {
            $filters = $this->session->$session_var;
        } else {
            $filters = new ScheduledPaymentsList();
        }

        $csv = new ScheduledPaymentsCsv(
            $this->zdb,
            $this->login
        );
        $csv->exportScheduledPayments($filters);

        $filepath = $csv->getPath();
        $filename = $csv->getFileName();

        return $this->sendResponse($response, $filepath, $filename);
    }
}
