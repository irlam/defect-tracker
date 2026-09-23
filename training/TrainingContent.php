<?php
declare(strict_types=1);

/**
 * Rich training content that sits alongside the database lesson metadata.
 * Keeping interactive scene definitions in code makes the demonstrations
 * versionable with the application UI without requiring a data migration.
 */
function trainingEnhancedContent(string $slug): array
{
    $lessons = [
        'create-a-defect' => [
            'intro' => 'Follow the same sequence used on the live Create Defect page: identify the issue, route it correctly, describe it clearly, locate it on the plan and submit it.',
            'demo' => [
                [
                    'title' => 'Start a new defect',
                    'caption' => 'Open Defect Ops and choose Create Defect. The form starts with the routing information used to send the task to the right team.',
                    'voice' => 'Start by opening Defect Ops and creating a new defect. The first section routes the issue to the correct project and contractor.',
                    'screen' => 'create-routing',
                    'focus' => 'project',
                ],
                [
                    'title' => 'Choose project and contractor',
                    'caption' => 'Select the active project first, then choose the contractor responsible for the work. The floor plans shown later are linked to the selected project.',
                    'voice' => 'Choose the project first and then the contractor. This keeps the defect attached to the correct job and routes the task to the contractor users.',
                    'screen' => 'create-routing',
                    'focus' => 'contractor',
                ],
                [
                    'title' => 'Set priority and due date',
                    'caption' => 'Choose Low, Medium, High or Critical priority. Add a due date where one is required by the job.',
                    'voice' => 'Set the priority to reflect urgency. Critical is for the most urgent items. Add a due date when the defect needs a specific completion target.',
                    'screen' => 'create-routing',
                    'focus' => 'priority',
                ],
                [
                    'title' => 'Write a useful defect description',
                    'caption' => 'Use a short title and a description that explains what is wrong, where it is and what good completion looks like.',
                    'voice' => 'Write a concise title, then describe the issue clearly. Include the location, what is wrong and enough information for the contractor to act without guessing.',
                    'screen' => 'create-description',
                    'focus' => 'description',
                ],
                [
                    'title' => 'Add evidence and location',
                    'caption' => 'Attach a clear photo, choose the project floor plan, then place the pin at the exact defect location.',
                    'voice' => 'Add photographic evidence and then locate the issue on the project floor plan. Accurate evidence and pin placement reduce questions later.',
                    'screen' => 'create-evidence',
                    'focus' => 'floorplan',
                ],
                [
                    'title' => 'Review and submit',
                    'caption' => 'Check the project, contractor, priority, description, evidence and pin before submitting. The defect then enters the contractor workflow.',
                    'voice' => 'Before you submit, check the routing, description, evidence and floor plan location. Once submitted, the defect is created and assigned into the workflow.',
                    'screen' => 'create-review',
                    'focus' => 'submit',
                ],
            ],
            'tips' => [
                ['title'=>'Make the title searchable','body'=>'Use the component and problem, for example “Apartment 204 – scratched kitchen worktop”, rather than a vague title such as “damage”.'],
                ['title'=>'Give enough context','body'=>'A contractor should be able to understand the issue from the description, photo and floor-plan pin without needing to call for the location.'],
                ['title'=>'Use priority consistently','body'=>'Reserve Critical for genuinely urgent items. Consistent priority makes the Defect Ops filters meaningful.'],
            ],
            'knowledge' => [
                ['question'=>'Why should the project be selected before the floor plan?', 'options'=>['It changes the user password','Floor plans are linked to the selected project','It automatically closes the defect'], 'answer'=>1],
                ['question'=>'Which items should be checked before submitting?', 'options'=>['Only the title','Project, contractor, priority, description, evidence and location','Only the contractor'], 'answer'=>1],
                ['question'=>'What makes a good defect description?', 'options'=>['A short vague note','Clear issue, location and enough detail to act','Only a photograph'], 'answer'=>1],
            ],
        ],

        'floor-plan-location' => [
            'intro' => 'The floor-plan selector separates navigation from pin placement so you can zoom into the right area without accidentally moving the defect marker.',
            'demo' => [
                [
                    'title' => 'Open the correct drawing',
                    'caption' => 'Select the floor plan that belongs to the project. The drawing opens fitted to the available workspace.',
                    'voice' => 'Choose the correct floor plan for the project. The plan opens fitted to the available screen so you can orientate yourself before zooming in.',
                    'screen' => 'plan-overview',
                    'focus' => 'plan',
                ],
                [
                    'title' => 'Navigate in Pan mode',
                    'caption' => 'Keep Pan selected while moving around the drawing. Drag on desktop or use touch gestures on mobile.',
                    'voice' => 'Use Pan mode to navigate around the drawing. Drag to move across the plan without placing or moving the defect pin.',
                    'screen' => 'plan-pan',
                    'focus' => 'pan',
                ],
                [
                    'title' => 'Zoom into the exact area',
                    'caption' => 'Use the mouse wheel on desktop or pinch on mobile. Zoom until the room or area is easy to identify.',
                    'voice' => 'Zoom into the area where the defect is located. On desktop use the mouse wheel. On a touch device use a pinch gesture.',
                    'screen' => 'plan-zoom',
                    'focus' => 'zoom',
                ],
                [
                    'title' => 'Switch to Place Pin',
                    'caption' => 'Only switch to Place Pin when the target area is visible. Click or tap the exact location.',
                    'voice' => 'Once the target area is clear, switch to Place Pin and click or tap the exact defect location.',
                    'screen' => 'plan-pin',
                    'focus' => 'pin',
                ],
                [
                    'title' => 'Fine-tune the marker',
                    'caption' => 'Drag the existing pin if it needs a small adjustment. Use Fit to return the drawing to the full view.',
                    'voice' => 'If the marker needs a small adjustment, drag it into position. The Fit control returns the full plan to the viewport when needed.',
                    'screen' => 'plan-fine',
                    'focus' => 'pin',
                ],
                [
                    'title' => 'Confirm the position',
                    'caption' => 'Save the selected location and return to the defect form. The stored coordinates are tied to the floor plan.',
                    'voice' => 'Confirm the location when you are happy with the marker. The position is stored with the floor plan and the defect.',
                    'screen' => 'plan-confirm',
                    'focus' => 'confirm',
                ],
            ],
            'tips' => [
                ['title'=>'Zoom before placing','body'=>'Place the pin only after zooming far enough to distinguish the correct room, elevation or work area.'],
                ['title'=>'Pan and Place Pin are different modes','body'=>'Use Pan for moving the drawing. Use Place Pin only when you are ready to set the defect location.'],
                ['title'=>'Think about the next person','body'=>'The pin should help a contractor find the defect quickly when they open the task on a phone.'],
            ],
            'knowledge' => [
                ['question'=>'Which mode should you use to move around the drawing?', 'options'=>['Place Pin','Pan','Submit'], 'answer'=>1],
                ['question'=>'When should you switch to Place Pin?', 'options'=>['Before opening a plan','When the exact target area is visible','After closing the defect'], 'answer'=>1],
                ['question'=>'How can an existing pin be fine-tuned?', 'options'=>['Drag the marker','Create a second project','Delete the contractor'], 'answer'=>0],
            ],
        ],

        'contractor-manager-lifecycle' => [
            'intro' => 'This lesson follows one defect from assignment through contractor completion evidence and manager review, including the rejection and resubmission path.',
            'demo' => [
                [
                    'title' => 'Defect is created and assigned',
                    'caption' => 'The manager creates the defect with project, contractor, priority, description, evidence and location. It enters the contractor task queue.',
                    'voice' => 'The workflow starts when a manager creates and assigns the defect. Contractor users linked to that contractor can then access the task.',
                    'screen' => 'life-created',
                    'focus' => 'created',
                ],
                [
                    'title' => 'Contractor opens the assigned task',
                    'caption' => 'The contractor reviews the description, floor-plan location, evidence and due information before starting work.',
                    'voice' => 'The contractor opens the assigned task and reviews the defect details, evidence and floor plan before starting the work.',
                    'screen' => 'life-contractor',
                    'focus' => 'task',
                ],
                [
                    'title' => 'Work is started',
                    'caption' => 'The contractor starts the defect so the workflow records that corrective work is in progress.',
                    'voice' => 'Starting the task records that work is in progress. This gives managers a clear view of current status.',
                    'screen' => 'life-progress',
                    'focus' => 'start',
                ],
                [
                    'title' => 'Completion evidence is submitted',
                    'caption' => 'After carrying out the work, the contractor uploads completion evidence and submits the defect for manager review.',
                    'voice' => 'When the work is complete, the contractor uploads clear completion evidence and submits the defect back for review.',
                    'screen' => 'life-evidence',
                    'focus' => 'evidence',
                ],
                [
                    'title' => 'Manager reviews the work',
                    'caption' => 'The manager compares the completion evidence against the original defect and chooses Accept or Reject.',
                    'voice' => 'The manager reviews the completion evidence against the original issue. If it meets the required standard it can be accepted. If not, it is rejected with a reason.',
                    'screen' => 'life-review',
                    'focus' => 'review',
                ],
                [
                    'title' => 'Reject and resubmit when needed',
                    'caption' => 'A rejected defect returns for further action. The rejection reason should explain exactly what still needs correcting.',
                    'voice' => 'If the work is rejected, give a clear reason. The contractor can then correct the remaining issue and submit new evidence.',
                    'screen' => 'life-reject',
                    'focus' => 'reject',
                ],
                [
                    'title' => 'Accept and close',
                    'caption' => 'When the evidence is satisfactory, accept the completion. The history records the lifecycle and the defect can be treated as closed.',
                    'voice' => 'When the completed work is satisfactory, accept it. The defect history records the lifecycle for future reference.',
                    'screen' => 'life-accepted',
                    'focus' => 'accepted',
                ],
            ],
            'tips' => [
                ['title'=>'Evidence should prove completion','body'=>'Completion photos should show the repaired area clearly enough for the manager to make a decision without ambiguity.'],
                ['title'=>'Rejection comments should be actionable','body'=>'Explain what remains wrong and what needs to be corrected rather than simply writing “rejected”.'],
                ['title'=>'Keep the lifecycle in the system','body'=>'Use the workflow buttons rather than informal messages so the current status and history remain visible to everyone involved.'],
            ],
            'knowledge' => [
                ['question'=>'What should happen after the contractor completes the work?', 'options'=>['Delete the defect','Upload completion evidence and submit for review','Create a new user'], 'answer'=>1],
                ['question'=>'What should a rejection reason contain?', 'options'=>['What remains wrong and what must be corrected','Only the word rejected','No explanation'], 'answer'=>0],
                ['question'=>'Who makes the review decision in this workflow?', 'options'=>['The manager/reviewer','Any anonymous visitor','The database automatically'], 'answer'=>0],
            ],
        ],
    ];

    return $lessons[$slug] ?? [];
}
