const workoutDayDataTransferType = 'application/x-wildforce-workout-day';

export function workoutDayDragDrop() {
    return {
        draggedWorkoutDayId: null,
        dropTarget: null,
        suppressNextClick: false,

        start(event, workoutDayId) {
            this.draggedWorkoutDayId = workoutDayId;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData(workoutDayDataTransferType, workoutDayId);
        },

        allowDrop(event, scheduledFor) {
            if (! this.draggedWorkoutDayId) {
                return;
            }

            event.dataTransfer.dropEffect = 'move';
            this.dropTarget = scheduledFor;
        },

        leave(event, scheduledFor) {
            if (event.currentTarget.contains(event.relatedTarget)) {
                return;
            }

            if (this.dropTarget === scheduledFor) {
                this.dropTarget = null;
            }
        },

        drop(event, scheduledFor) {
            const workoutDayId = this.draggedWorkoutDayId ?? event.dataTransfer.getData(workoutDayDataTransferType);

            if (! workoutDayId) {
                return;
            }

            this.$dispatch('workout-day-dropped', { workoutDayId, scheduledFor });
            this.reset();
        },

        end() {
            this.suppressNextClick = this.draggedWorkoutDayId !== null;
            this.reset();

            window.setTimeout(() => {
                this.suppressNextClick = false;
            }, 150);
        },

        suppressClickAfterDrag(event) {
            if (! this.suppressNextClick) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            this.suppressNextClick = false;
        },

        isDragging(workoutDayId) {
            return this.draggedWorkoutDayId === workoutDayId;
        },

        isDropTarget(scheduledFor) {
            return this.dropTarget === scheduledFor;
        },

        reset() {
            this.draggedWorkoutDayId = null;
            this.dropTarget = null;
        },
    };
}
