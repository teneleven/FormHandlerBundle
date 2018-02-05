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
    public function handleAction(Request $request, $type, array $config = array())
    {
        //Ensure this is a defined type
        if (!in_array($type, $this->getParameter('teneleven_form_handler_types'))) {
            throw $this->createNotFoundException();
        }

        //Get Config
        $config = $this->createConfig($type, $config);

        //Create Form
        $form = $this->createForm($type);

        //Bind Form
        $form->handleRequest($request);

        //Get Form Name
        $name = (string) $form->getName();

        //Check if Form is Valid
        if ($form->isValid()) {

            //Get Form Submission
            $submission = $form->getData();

            //Find all attachments
            $attachments = $this->findAttachments($submission);

            //Check if Submission is Array
            if (is_array($submission)) {

                //Create Submission from array
                $submission = $this->createSubmission($type, $submission);
            }

            //Get Request Values for Form
            $values = $request->request->has($name) ? $request->request->get($name) : array();

            //Allow Config to be overriden
            $config = $this->overrideConfig($config, $values);

            //Save the Submission
            $this->save($submission);

            //Get Reflection
            $reflect = new \ReflectionClass($submission);

            //Get Short name
            $class = $reflect->getShortName();

            $params =  array(
                //Pass Raw Form Values
                'data' => $values,
                //Pass Object keyed by Class name
                strtolower($class) => $submission, 
                //Pass form
                'form' => $form->createView(),
                //Pass Attachments
                'attachments' => $attachments
            );

            //Send Notification Email based on config
            $this->sendEmail($config, $params);

            //Return Thanks Page
            return $this->render(
                $config['thanks_template'],
                $params
            );         
        }

        //Return to Form Page with Errors
        return $this->render(
            $config['template'],
            array('form' => $form->createView())
        );
    }

    /**
     * Create New Form Submission for passed array
     * 
     * @param  [type] $type Container type string
     * @param  array  $data Values from form
     * 
     * @return Submission 
     */
    protected function createSubmission($type, array $data)
    {
        $submission = new Submission();
        $submission->setType($type);
        $submission->setData(serialize((array) $data));

        return $submission;
    }

    /**
     * Save Submission
     * 
     * @param  [type] $submission [description]
     */
    protected function save($object)
    {
        $em = $this->getDoctrine()->getManager();
        $em->persist($object);
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
    public function overrideConfig(array $config, $values)
    {
        if (isset($config['values']) AND count($config['values'])) {
            foreach ($config['values'] as $key => $field_values) {
                foreach ($field_values as $value => $new_config) {
                    if (isset($values[$key]) AND  $value == $values[$key]) {
                        $config = (array) array_merge($config, $new_config);
                    }
                }
            }
        }

        //No longer needed
        unset($config['values']);

        return (array) $config;
    }

    public function createConfig($type, $config = array())
    {
        //Get Base config
        $config = (array) array_merge($config, (array) $this->container->getParameter('teneleven_form_handler', array()));

        //Create Parameter key for Type
        $type_parameter_key = (string) sprintf('teneleven_form_handler.%s', $type);

        //Check if Config is set for this Type
        if ($this->container->hasParameter($type_parameter_key)) {

            //Get Config for that type
            $type_config = $this->container->getParameter($type_parameter_key, array());

            //Merge into master config
            $config = (array) array_merge($config, $type_config);
        } 

        return $config;
    }

    /**
     * Find attachments in data array
     * 
     * @param  array &$data [description]
     * @return [type]       [description]
     */
    public function findAttachments(array &$data)
    {
        $attachments = array();

        foreach ($data as $key => $value) {
            if ($value instanceof UploadedFile) {
                $attachments[] = $value;
                unset($data[$key]);
            }
        }

        return (array) $attachments;
    }

    /**
     * Create the Form
     * 
     * @param string $type     Type name of Form
     * @param string $template String of template filename
     * 
     * @return Response
     */
    public function formAction($type, $submission = null, $template = 'TenelevenFormHandlerBundle:Submission:_form.html.twig')
    {
        $form = $this->createForm(
            (string) $type,
            $submission, 
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
     * @return bool
     */
    protected function sendEmail(array $config, array $params = array())
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
                        $params
                    )
                )
            ;

            //Attach any Files
            if (isset($params['attachments']) AND count($params['attachments'])) {
                foreach ($params['attachments'] as $file) {
                    $attachment = \Swift_Attachment::fromPath($file->getRealPath())->setFilename($file->getClientOriginalName());
                    $message->attach($attachment);                    
                }
            }

            return (bool) $this->get('mailer')->send($message);

        } catch(Exception $e) {
            //@todo Need to do something here
        }
    }
}
