FROM python:3.11-slim
ENV PYTHONUNBUFFERED=1
RUN pip install xai_sdk
RUN pip install --no-cache-dir deepeval openai
RUN pip install fastapi[standard]
RUN pip install deepeval
RUN pip install openai
RUN pip install beautifulsoup4
CMD ["python", "main.py"]