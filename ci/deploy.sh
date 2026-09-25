#!/bin/bash

set -e

PROJECT_ID="wildforce-prod"
REGION="europe-west1"
REPOSITORY="cloud-run-source-deploy"
IMAGE_NAME="wildforce"
SERVICE_NAME="wildforce-prod"

TAG=$(date +"%Y%m%d-%H%M%S")

IMAGE="${REGION}-docker.pkg.dev/${PROJECT_ID}/${REPOSITORY}/${IMAGE_NAME}:${TAG}"

echo "🚀 Deploying ${IMAGE}"
echo

echo "▶ Building Docker image..."
docker build \
  --platform linux/amd64 \
  -f ci/Dockerfile \
  -t "${IMAGE}" \
  .

echo
echo "▶ Pushing image..."
docker push "${IMAGE}"

echo
echo "▶ Deploying to Cloud Run..."
gcloud run deploy "${SERVICE_NAME}" \
  --image "${IMAGE}" \
  --region "${REGION}" \
  --allow-unauthenticated \
  --env-vars-file ci/.env.cloudrun

echo
echo "✅ Deployment complete!"
echo "Image: ${IMAGE}"