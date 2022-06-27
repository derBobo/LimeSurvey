<?php

/* @var $survey Survey */
/* @var $adminmenu */
/* @var $languageToTranslate  string  e.g. 'de' 'it' ... */
/* @var $additionalLanguages array */

?>

<div class="side-body <?php echo getSideBodyClass(false); ?>">
    <h3><span class="fa fa-language text-success" ></span>&nbsp;&nbsp;<?php eT("Translate survey"); ?></h3>
    <div class="row">
        <div class="col-lg-12 content-right">
            <?php
            echo CHtml::form(
                ["quickTranslation/index",'surveyid' => $survey->sid],
                'get',
                ['id' => 'translatemenu', 'class' => 'form-inline']
            );
            ?>
            <?php //echo $adminmenu; ?>

            <!-- select box for languages 'class' => 'form-group' -->
            <div class="from-group">
                <?php
                echo CHtml::tag('label', array('for' => 'translationlanguage', 'class' => 'control-label'), gT("Translate to") . ":");
                echo CHtml::openTag(
                    'select',
                    array(
                        'id' => 'translationlanguage',
                        'name' => 'lang',
                        'class' => 'form-control',
                        'onchange' => "$(this).closest('form').submit();"
                    )
                );
                if (count($additionalLanguages) > 1) {
                    echo CHtml::tag(
                        'option',
                        array(
                            'selected' => empty($languageToTranslate),
                            'value' => ''
                        ),
                        gT("Please choose...")
                    );
                }
                foreach ($additionalLanguages as $lang) {
                    $supportedLanguages = getLanguageData(false, Yii::app()->session['adminlang']);
                    $tolangtext = $supportedLanguages[$lang]['description'];
                    echo CHtml::tag(
                        'option',
                        array(
                            'selected' => ($languageToTranslate == $lang),
                            'value' => $lang
                        ),
                        $tolangtext
                    );
                }
                ?>
            </div>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12 content-right">
            <h4>
                <?php eT("Translate survey");?>
            </h4>
        </div>
    </div>
<?php
