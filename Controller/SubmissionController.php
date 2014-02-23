<?php
/**
 * Submission controller.
 *
 * @category None
 * @package  TenelevenFormHandlerBundle
 * @author   Justin Hilles <justin@1011i.com>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version  0.1
 * @link     none
 *
 */

namespace Teneleven\Bundle\FormHandlerBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use Teneleven\Bundle\FormHandlerBundle\Entity\Submission;
use Teneleven\Bundle\FormHandlerBundle\Form\SubmissionType;

/**
 * Submission controller.
 *
 * @category None
 * @package  TenelevenFormHandlerBundle
 * @author   Justin Hilles <justin@1011i.com>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version  0.1
 * @link     none
 *
 */
class SubmissionController extends Controller
{
    protected $attachments = array();

    /**
     * Lists all Submission entities.
     *
     * @return Response
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $entities = $em->getRepository('TenelevenFormHandlerBundle:Submission')->findAll();

        return $this->render(
            'TenelevenFormHandlerBundle:Submission:index.html.twig', array(
                'entities' => $entities,
            )
        );
    }

    /**
     * Finds and displays a Submission entity.
     *
     * @param int $id ID Submission
     * 
     * @return Response
     */
    public function showAction($id)
    {
        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('TenelevenFormHandlerBundle:Submission')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Submission entity.');
        }

        $entity->setIsViewed(true);

        $em = $this->getDoctrine()->getManager();
        $em->persist($entity);
        $em->flush();

        $data = unserialize($entity->getData());

        $form = $this->createForm($entity->getType(), $data);

        return $this->render(
            'TenelevenFormHandlerBundle:Submission:show.html.twig', array(
                'form'      => $form->createView(),
                'entity'    => $entity
            )
        );
    }

    /**
     * Handle all Form Submissions
     * 
     * @param string  $type    Form Type
     * @param Request $request Request
     * 
     * @return null
     */
    public function handleAction($type, Request $request)
    {
        $form = $this->createForm($type);

        $form->handleRequest($request);

        $config = $this->container->getParameter(sprintf('teneleven_form_handler.%s', $type));

        if ($form->isValid()) {

            $data = $form->getData();

            if (is_array($data)) {
                $config = $this->modifyConfigForValues($config, $data);

                $attachments = $this->findAttachments($data);

                $data = $this->createSubmission($type, $data);
            }

            $this->saveSubmission($data);

            $this->sendNotificationEmail($config, $form, $attachments, $data);

            return $this->render(
                $config['thanks_template'],
                array(
                    'submission' => $data, 
                    'form' => $form->createView()
                )
            );         
        }

        return $this->render(
            $config['template'],
            array(
                'form' => $form->createView()
            )
        );
    }

    public function createSubmission($type, $data)
    {
        $submission = new Submission();
        $submission->setType($type);
        $submission->setData(serialize($data));
    }

    public function saveSubmission($data)
    {
        $em = $this->getDoctrine()->getManager();
        $em->persist($data);
        $em->flush();        
    }

    /**
     * Check for  a new config based on values
     * 
     * @param  [type] $config [description]
     * @param  [type] $values [description]
     * 
     * @return $config
     */
    public function modifyConfigForValues($config, $values)
    {
        if (!isset($config['values'])) {
            return $config;
        }

        if (!count($config['values'])) {
            return $config;
        }

        foreach ($config['values'] as $key => $field_values) {
            foreach ($field_values as $value => $new_config) {
                if (isset($values[$key]) AND $values[$key] == $value) {
                    $config = array_merge($config, $new_config);
                }
            }
        }

        return $config;
    }

    /**
     * Find attachments in data array
     * 
     * @param  array &$data [description]
     * @return [type]       [description]
     */
    public function findAttachments(&$data)
    {
        $attachments = array();

        foreach ($data as $key => $value) {
            if ($value instanceof UploadedFile) {
                $attachments[] = $value;
                unset($data[$key]);
            }
        }

        return $attachments;
    }

    /**
     * Create the Form
     * 
     * @param string $type     Type name of Form
     * @param string $template String of template filename
     * 
     * @return Response
     */
    public function formAction($type, $template = 'TenelevenFormHandlerBundle:Submission:_form.html.twig')
    {
        $form = $this->createForm(
            $type,
            null, 
            array('action' => $this->generateUrl('teneleven_formhandler_handle', array('type' => $type)))
        );

        return $this->render(
            $template, 
            array(
                'form' => $form->createView(),
                'type' => $type
            )
        );
    }

    /**
     * Sends Email following parameters in config
     * 
     * @param string $type Service key of Form
     * @param Object $form Form
     * 
     * @return null
     */
    protected function sendNotificationEmail($config, $form, array $attachments = array(), $submission)
    {
        try {
            //Create new Message
            $message = \Swift_Message::newInstance()
                ->setSubject($config['subject'])
                ->setFrom($config['from'])
                ->setTo($config['to'])
                ->setContentType($config['content_type'])
                ->setBody(
                    $this->renderView(
                        $config['email_template'],
                        array(
                            'form' => $form->createView(), 
                            'submission' => $submission
                        )
                    )
                )
            ;

            //Attach any Files
            foreach ($attachments as $file) {
                $attachment = \Swift_Attachment::fromPath($file->getRealPath())->setFilename($file->getClientOriginalName());
                $message->attach($attachment);                    
            }

            $result = $this->get('mailer')->send($message);

        } catch(Exception $e) {
            //@todo Need to do something here
        }
    }
}
