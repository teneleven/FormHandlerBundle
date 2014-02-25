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
    public function handleAction($type, Request $request, $config = array())
    {
        //Get Base config
        $config = array_merge($config, $this->container->getParameter('teneleven_form_handler', array()));

        //Create Form
        $form = $this->createForm($type);

        //Bind Form
        $form->handleRequest($request);

        //Get Form Name
        $name = $form->getName();

        //Create Parameter key for Type
        $type_parameter_key = sprintf('teneleven_form_handler.%s', $type);

        //Check if Config is set for this Type
        if ($this->container->hasParameter($type_parameter_key)) {

            //Get Config for that type
            $type_config = $this->container->getParameter($type_parameter_key);

            //Merge into master config
            $config = array_merge($config, $type_config);
        }

        //Get Data Class for Form,  if any
        $data_class = $form->getConfig()->getDataClass();

        //Check if Form is Valid
        if ($form->isValid()) {

            //Get Form Submission
            $submission = $form->getData();

            //Check if Submission is Array
            if (is_array($submission)) {

                //Create Submission from array
                $submission = $this->createSubmission($type, $submission);
            }

            //Get Request Values for Form
            $values = $request->request->has($name) ? $request->request->get($name) : array();

            //Find all attachments
            $attachments = $this->findAttachments($values);

            //Allow Config to be overriden
            $config = $this->modifyConfigForValues($config, $values);

            //Save the Submission
            $this->saveSubmission($submission);

            //Send Notification Email based on config
            $this->sendNotificationEmail($config, $form, $attachments, $submission);

            //Return Thanks Page
            return $this->render(
                $config['thanks_template'],
                array(
                    'data' => $submission, 
                    'form' => $form->createView()
                )
            );         
        }

        //Return to Form Page with Errors
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

        return $submission;
    }

    public function saveSubmission($submission)
    {
        $em = $this->getDoctrine()->getManager();
        $em->persist($submission);
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
                if (isset($values[$key]) AND  $value == $values[$key]) {
                    $config = array_merge($config, $new_config);
                }
            }
        }

        //No longer needed
        unset($config['values']);

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
                            'data' => $submission
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
