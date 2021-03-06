<?php
/**
 * This file contains a form creator to create a Conversation
 *
 * @license    https://github.com/allejo/bzion/blob/master/LICENSE.md GNU General Public License Version 3
 */

namespace BZIon\Form\Creator;

use BZIon\Form\Type\AdvancedModelType;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Form creator for creating new conversations
 */
class ConversationFormCreator extends ModelFormCreator
{
    /**
     * {@inheritdoc}
     */
    protected function build($builder)
    {
        $notBlank = array('constraints' => new NotBlank());

        return $builder
            ->add('Recipients', new AdvancedModelType(array('player', 'team')), array(
                'constraints' => new Count(array(
                    'min'        => 2, // myself is always included
                    'minMessage' => 'You need to specify the recipients of your message'
                )),
                'multiple' => true,
                'include'  => $this->editing,
            ))
            ->add('Subject', 'text', $notBlank)
            ->add('Message', 'textarea', $notBlank)
            ->add('Send', 'submit')

            // Prevents JS from going crazy if we load a page with AJAX
            ->setAction(\Service::getGenerator()->generate('message_compose'));
    }
}
