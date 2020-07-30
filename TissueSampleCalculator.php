<?php
namespace Stanford\TissueSampleCalculator;

require_once "emLoggerTrait.php";

/**
 * Class TissueSampleCalculator
 * @package Stanford\TissueSampleCalculator
 * @property string $instrument
 * @property string $sampleRecordIdField
 * @property array $instances
 * @property array $record
 * @property array $sampleRecord
 * @property int $sampleEventId
 * @property int $eventId
 * @property array $optionNumbers
 */
class TissueSampleCalculator extends \ExternalModules\AbstractExternalModule
{

    use emLoggerTrait;

    private $instrument;

    private $sampleRecordIdField;

    private $instances;

    private $record;

    private $sampleRecord;

    private $sampleEventId;

    private $eventId;

    private $optionNumbers;


    public function __construct()
    {
        parent::__construct();
        // Other code to run when object is instantiated

        if (isset($_GET['pid'])) {
            $this->setInstances();

            $this->setInstrument($this->getProjectSetting('tissue-retrieval-instrument'));

            $this->setSampleRecordIdField($this->getProjectSetting('sample-record-id'));

            $this->setSampleEventId($this->getProjectSetting('sample-event-id'));
        }
    }

    /**
     * this will pull sample record and display how much left to be used for each retrieval type
     * @param int $project_id
     * @param string|null $record
     * @param string $instrument
     * @param int $event_id
     * @param int|null $group_id
     * @param int $repeat_instance
     */
    public function redcap_data_entry_form_top(
        int $project_id,
        string $record = null,
        string $instrument,
        int $event_id,
        int $group_id = null,
        int $repeat_instance = 1
    ) {
        try {
            if ($instrument == $this->getInstrument()) {

                //set event id
                $this->setEventId($event_id);

                // we need to set tissue retrieval record
                if (!is_null($record)) {
                    $this->setRecord($record);

                    //set parent sample record
                    $this->setSampleRecord($this->getRecord()[$this->getEventId()][$this->getSampleRecordIdField()]);
                } elseif (isset($_GET['parent'])) {
                    //set parent sample record
                    $this->setSampleRecord(filter_var($_GET['parent'], FILTER_SANITIZE_STRING));
                }

                $this->setRetrievalTypeNumbersFromSampleRecord();
            }
        } catch (\Exception $e) {
            $this->emError($e->getMessage());
            \REDCap::logEvent("ERROR/EXCEPTION occurred " . $e->getMessage(), '', null, null);
        }
    }

    public function setRetrievalTypeNumbersFromSampleRecord()
    {
        global $Proj;
        $records = $this->getSampleRecord();
        $fields = array();
        if (!empty($records)) {
            $record = $records[$this->getSampleEventId()];
            foreach ($this->getInstances() as $instance) {
                // we just need to manupilate the options for issue types before render :)
                $options = parseEnum($Proj->metadata[$instance['tissue-type']]['element_enum']);

                $option = $options[$instance['tissue-type-option']];
                if (is_null($option)) {
                    throw new \Exception("MISCONFIGURATION: could not find defined value " . $instance['tissue-type-option']);
                }
                $text = $option . ' left records(' . $record[$instance['sample-field']] . ')';

                $Proj->metadata[$instance['tissue-type']]['element_enum'] = str_replace($option, $text,
                    $Proj->metadata[$instance['tissue-type']]['element_enum']);

            }
            $this->setOptionNumbers($fields);
        }
    }

    /**
     * this function will update sample record when new retrieval is used.
     * @param int $project_id
     * @param string|null $record
     * @param string $instrument
     * @param int $event_id
     * @param int|null $group_id
     * @param string|null $survey_hash
     * @param int|null $response_id
     * @param int $repeat_instance
     */
    public function redcap_save_record(
        int $project_id,
        string $record = null,
        string $instrument,
        int $event_id,
        int $group_id = null,
        string $survey_hash = null,
        int $response_id = null,
        int $repeat_instance = 1
    ) {
        try {
            if ($instrument == $this->getInstrument()) {

                //set event id
                $this->setEventId($event_id);

                // we need to set tissue retrieval record
                if (!is_null($record)) {
                    $this->setRecord($record);

                    //set parent sample record
                    $this->setSampleRecord($this->getRecord()[$this->getEventId()][$this->getSampleRecordIdField()]);
                }

            }
        } catch (\Exception $e) {
            $this->emError($e->getMessage());
            \REDCap::logEvent("ERROR/EXCEPTION occurred " . $e->getMessage(), '', null, null);
            echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
        }
    }

    /**
     * @return string
     */
    public function getInstrument()
    {
        return $this->instrument;
    }

    /**
     * @param string $instrument
     */
    public function setInstrument($instrument)
    {
        $this->instrument = $instrument;
    }

    /**
     * @return string
     */
    public function getSampleRecordIdField()
    {
        return $this->sampleRecordIdField;
    }

    /**
     * @param string $sampleRecordIdField
     */
    public function setSampleRecordIdField($sampleRecordIdField)
    {
        $this->sampleRecordIdField = $sampleRecordIdField;
    }


    /**
     * @return array
     */
    public function getInstances()
    {
        return $this->instances;
    }

    /**
     * @param array $instances
     */
    public function setInstances()
    {
        $this->instances = $this->getSubSettings('instance', $this->getProjectId());;
    }

    /**
     * @return array
     */
    public function getRecord()
    {
        return $this->record;
    }

    /**
     * @param string $record
     */
    public function setRecord($record)
    {
        $param = array(
            'project_id' => $this->getProjectId(),
            'records' => [$record]
        );

        $this->record = \REDCap::getData($param);
    }

    /**
     * @return array
     */
    public function getSampleRecord()
    {
        return $this->sampleRecord;
    }

    /**
     * @param array $sampleRecord
     */
    public function setSampleRecord($sampleRecord)
    {
        $param = array(
            'project_id' => $this->getProjectId(),
            'records' => [$sampleRecord]
        );
        $r = \REDCap::getData($param);
        $this->sampleRecord = $r[$sampleRecord];
    }

    /**
     * @return int
     */
    public function getSampleEventId()
    {
        return $this->sampleEventId;
    }

    /**
     * @param int $sampleEventId
     */
    public function setSampleEventId($sampleEventId)
    {
        $this->sampleEventId = $sampleEventId;
    }

    /**
     * @return int
     */
    public function getEventId()
    {
        return $this->eventId;
    }

    /**
     * @param int $eventId
     */
    public function setEventId($eventId)
    {
        $this->eventId = $eventId;
    }

    /**
     * @return array
     */
    public function getOptionNumbers()
    {
        return $this->optionNumbers;
    }

    /**
     * @param array $optionNumbers
     */
    public function setOptionNumbers($optionNumbers)
    {
        $this->optionNumbers = $optionNumbers;
    }


    /**
     * @param string $path
     */
    public function includeFile($path)
    {
        include_once $path;
    }
}
