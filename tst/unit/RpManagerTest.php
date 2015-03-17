<?php

require_once('AbstractUnitTester.php');

/**
 * Test the RpManager functionality.
 *
 * @author Dayan Paez
 * @created 2015-03-17
 */
class RpManagerTest extends AbstractUnitTester {

  /**
   * Test the attendee adding logic: make sure that existing list of
   * attendees is unaffected by addition of new ones.
   *
   */
  public function testSetAttendees() {
    $reg = self::getRegatta(Regatta::SCORING_STANDARD);

    $schools = DB::getSchools();
    if (count($schools) == 0) {
      throw new InvalidArgumentException("No schools in system!");
    }

    $sailors = array();
    $attempts = 0;
    while (count($sailors) < 2 && $attempts < 10) {
      $attempts++;
      $school = $schools[rand(0, count($schools) - 1)];
      $sailors = $school->getSailors();
    }

    if (count($sailors) == 0) {
      throw new InvalidArgumentException("No sailors found.");
    }

    $someSailors = array();
    for ($i = 0; $i < count($sailors) - 1 && $i < 5; $i++) {
      $someSailors[] = $sailors[$i];
    }

    $rpManager = $reg->getRpManager();
    $rpManager->setAttendees($school, $someSailors);

    $attendees = $rpManager->getAttendees($school);
    $this->assertEquals(count($someSailors), count($attendees), "Comparing initially set attendee list");

    $attendeeIds = array();
    foreach ($attendees as $attendee) {
      $attendeeIds[$attendee->sailor->id] = $attendee->id;
    }

    // Add a new one, and remove last one
    $newSailor = $sailors[$i];
    $someSailors[] = $newSailor;
    array_shift($someSailors);
    $rpManager->setAttendees($school, $someSailors);

    $attendees = $rpManager->getAttendees($school);
    $this->assertEquals(count($someSailors), count($attendees), "Comparing new attendee list");

    foreach ($attendees as $attendee) {
      if ($attendee->sailor->id != $newSailor->id) {
        $this->assertContains($attendee->id, $attendeeIds, "Is old attendee still intact?");
      }
    }
  }
}